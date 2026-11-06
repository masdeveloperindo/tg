<?php

namespace App\Actions\Rebates;

use App\Actions\Products\ProductAssets;
use App\Models\Broker;
use App\Support\Audit;
use App\Support\PublicContent;
use App\Support\SafeHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ManageBrokers
{
    public function save(?int $id, array $input, ?UploadedFile $logo = null): Broker
    {
        $admin = Finance::admin();
        $data = Validator::make($input, ['name' => 'required|string|max:120', 'ib_link' => 'required|string|max:1000', 'rate_usd_per_lot' => 'required|string', 'valid_from' => 'required|date_format:Y-m-d', 'terms' => 'nullable|string|max:20000', 'is_active' => 'required|boolean'])->validate();
        $data['rate_usd_per_lot'] = Finance::decimal($data['rate_usd_per_lot'], 4, 14);
        $data['ib_link'] = PublicContent::brokerLink($data['ib_link']) ?? throw ValidationException::withMessages(['ib_link' => 'Gunakan URL HTTPS pada hostname referral yang disetujui owner.']);
        $data['terms'] = SafeHtml::clean($data['terms'] ?? '');
        $path = $logo ? app(ProductAssets::class)->image($logo) : null;
        try {
            return DB::transaction(function () use ($id, $data, $path, $admin) {
                $broker = $id ? Broker::whereKey($id)->lockForUpdate()->firstOrFail() : new Broker;
                $summary = ['rate_after' => $data['rate_usd_per_lot'], 'effective_after' => $data['valid_from'], 'content_changed' => true];
                if ($broker->exists) {
                    $summary += ['rate_before' => $broker->rate_usd_per_lot, 'effective_before' => $broker->valid_from->format('Y-m-d')];
                }
                $broker->forceFill($data);
                if ($path) {
                    $broker->logo_path = $path;
                }
                $broker->save();
                Audit::record($admin, 'broker.saved', $broker, $summary);

                return $broker;
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }
}
