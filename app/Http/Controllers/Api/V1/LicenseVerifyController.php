<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LicenseApi\Envelope;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseLog;
use App\Support\RequestId;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LicenseVerifyController
{
    public function __invoke(Request $request, Envelope $signer)
    {
        $started = hrtime(true);
        $data = $request->json()->all();
        $validator = Validator::make($data, [
            'protocol' => ['required', 'integer', 'in:1'],
            'license_key' => ['required', 'string', 'regex:/\A(?:[a-fA-F0-9]{4}-){7}[a-fA-F0-9]{4}\z/'],
            'product_id' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'account_number' => ['required', 'string', 'regex:/\A[1-9][0-9]{0,23}\z/'],
            'server' => ['required', 'string', 'max:100', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'account_type' => ['required', 'in:real,demo'],
            'version' => ['required', 'string', 'max:40', 'regex:/\A[0-9]+\.[0-9]+(?:\.[0-9]+)?\z/'],
            'timestamp' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
            'nonce' => ['required', 'string', 'min:22', 'max:128', 'regex:/\A[A-Za-z0-9_-]+\z/'],
        ]);
        $fields = ['protocol', 'license_key', 'product_id', 'account_number', 'server', 'account_type', 'version', 'timestamp', 'nonce'];
        if ($validator->fails() || array_diff(array_keys($data), $fields)) {
            return $this->error('invalid_request', 422);
        }
        $nonce = base64_decode(strtr($data['nonce'], '-_', '+/'), true);
        if (! is_string($nonce) || strlen($nonce) < 16 || Envelope::encode($nonce) !== $data['nonce'] || abs(CarbonImmutable::parse($data['timestamp'])->timestamp - now()->timestamp) > 300) {
            return $this->error('invalid_request', 422);
        }
        $base = rtrim((string) config('app.url'), '/');
        abort_unless(filter_var($base, FILTER_VALIDATE_URL) && parse_url($base, PHP_URL_SCHEME) === 'https', 503);
        // Durable unique insert: replay remains rejected even if the verdict response is lost.
        try {
            DB::table('api_nonces')->insert(['nonce_sha256' => hash('sha256', $data['nonce']), 'expires_at' => now()->addMinutes(10), 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return $this->error('replay_detected', 409);
        }

        return DB::transaction(function () use ($data, $request, $signer, $base, $started) {
            $license = License::where('key_hash', hash('sha256', strtoupper($data['license_key'])))->lockForUpdate()->first();
            $matched = $license && $license->product_id === (int) $data['product_id'];
            $version = $matched ? $license->product->versions()->where('is_active', true)->whereNotNull('released_at')->where('released_at', '<=', now())->first() : null;
            $minimum = $version?->min_supported_version ?? $version?->version;
            $status = match (true) {
                ! $matched => 'invalid',
                $license->user->status->value !== 'active', $license->user->must_change_password, $license->user->role->value !== 'client' => 'invalid',
                $license->product->type->value === 'indicator' && ! config('tradergaruda.indicator_sales_enabled') => 'invalid',
                $license->status->value === 'revoked' => 'revoked',
                $license->status->value === 'suspended' => 'suspended',
                now()->gte($license->expires_at) => 'expired',
                $license->mt5_account !== $data['account_number'], $license->account_type->value !== $data['account_type'] => 'account_mismatch',
                $license->mt5_server !== $data['server'] => 'server_mismatch',
                ! $version, ! preg_match('/\A[0-9]+\.[0-9]+(?:\.[0-9]+)?\z/', (string) $minimum), version_compare($data['version'], (string) $minimum, '<') => 'update_required',
                default => 'valid',
            };
            $envelope = $signer->sign([
                'status' => $status, 'product_id' => (int) $data['product_id'], 'account_number' => $data['account_number'],
                'server' => $data['server'], 'account_type' => $data['account_type'], 'version' => $data['version'],
                'expires_at' => $matched ? $license->expires_at->utc()->format('Y-m-d\TH:i:s\Z') : null,
                'latest_version' => $version?->version, 'min_supported_version' => $minimum,
                'api_base_url' => $base, 'server_timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'request_nonce' => $data['nonce'], 'request_id' => RequestId::current(),
            ]);
            (new LicenseLog)->forceFill([
                'license_id' => $matched ? $license->id : null, 'account_number_masked' => '****'.substr($data['account_number'], -4),
                'product_version' => $data['version'], 'event' => 'verify', 'result' => $status,
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key')),
                'request_id' => RequestId::current(), 'latency_ms' => (int) ((hrtime(true) - $started) / 1000000),
            ])->save();
            if ($status === 'valid') {
                $activation = LicenseActivation::where('license_id', $license->id)->where('account_number', $license->mt5_account)->first() ?? new LicenseActivation;
                $activation->forceFill(['license_id' => $license->id, 'account_number' => $license->mt5_account, 'server_name' => $license->mt5_server, 'account_type' => $license->account_type, 'product_version' => $data['version'], 'last_seen_at' => now()])->save();
            }

            return response()->json($envelope);
        }, 3);
    }

    private function error(string $code, int $status)
    {
        return response()->json(['code' => $code, 'request_id' => RequestId::current()], $status);
    }
}
