<?php

namespace App\Actions\Operations;

use App\Actions\Orders\CalendarPricing;
use App\Actions\Rebates\Finance;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class Configuration
{
    public const ANOMALY = ['window_minutes' => 60, 'cooldown_minutes' => 60, 'mismatch_threshold' => 5, 'ip_threshold' => 5, 'burst_threshold' => 60, 'expired_threshold' => 10];

    public static function json(string $key, array $fallback = []): array
    {
        try {
            $v = json_decode(Setting::where('key', $key)->first()?->value_encrypted ?? '', true, 32, JSON_THROW_ON_ERROR);

            return is_array($v) ? $v : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function save(array $pricing, array $methods, string $telegram, array $anomaly, array $site): void
    {
        $admin = Finance::admin();
        $pricing = app(CalendarPricing::class)->rules($pricing);
        $pricing['quote_minutes'] = (int) $pricing['quote_minutes'];
        $pricing['payment_minutes'] = (int) $pricing['payment_minutes'];
        Validator::make(['methods' => $methods], ['methods' => 'required|array|min:1|max:10', 'methods.*.id' => ['required', 'string', 'distinct', 'regex:/\A[a-z0-9-]{1,64}\z/'], 'methods.*.label' => 'required|string|max:100', 'methods.*.account_holder' => 'required|string|max:120', 'methods.*.account_number' => ['required', 'string', 'regex:/\A[A-Za-z0-9 -]{3,64}\z/'], 'methods.*.instructions' => 'nullable|string|max:500'])->validate();
        Validator::make(compact('telegram', 'anomaly', 'site'), ['telegram' => ['nullable', 'string', 'regex:/\A@?[A-Za-z0-9_]{5,32}\z/'], 'anomaly' => 'required|array', 'anomaly.window_minutes' => 'required|integer|between:10,1440', 'anomaly.cooldown_minutes' => 'required|integer|between:10,10080', 'anomaly.mismatch_threshold' => 'required|integer|between:1,100000', 'anomaly.ip_threshold' => 'required|integer|between:1,100000', 'anomaly.burst_threshold' => 'required|integer|between:1,100000', 'anomaly.expired_threshold' => 'required|integer|between:1,100000', 'site.title' => 'required|string|max:191', 'site.description' => 'required|string|max:320'])->validate();
        $anomaly = array_map('intval', array_intersect_key($anomaly, self::ANOMALY));
        DB::transaction(function () use ($admin, $pricing, $methods, $telegram, $anomaly, $site) {
            foreach (['checkout.pricing' => $pricing, 'checkout.payment_methods' => $methods, 'telegram_admin_username' => $telegram, 'license.anomaly' => $anomaly, 'public.site' => array_intersect_key($site, array_flip(['title', 'description']))] as $key => $value) {
                $row = Setting::where('key', $key)->lockForUpdate()->first() ?? new Setting;
                $row->forceFill(['key' => $key, 'value_encrypted' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value, 'is_secret' => true])->save();
                Audit::record($admin, 'configuration.saved', $row, ['content_changed' => true]);
            }
        }, 3);
    }

    public static function anomaly(): array
    {
        $values = array_replace(self::ANOMALY, self::json('license.anomaly'));
        $result = [];
        foreach (self::ANOMALY as $key => $default) {
            $v = $values[$key] ?? $default;
            $result[$key] = is_int($v) && $v >= 1 && $v <= 100000 ? $v : $default;
        }

        return $result;
    }
}
