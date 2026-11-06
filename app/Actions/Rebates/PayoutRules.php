<?php

namespace App\Actions\Rebates;

use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PayoutRules
{
    public function get(bool $locked = false): ?array
    {
        $query = Setting::where('key', 'rebate.payout');
        if ($locked) {
            $query->sharedLock();
        }
        $raw = $query->first()?->value_encrypted;
        if (! is_string($raw)) {
            return null;
        }
        try {
            return $this->validated(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
        } catch (\JsonException|ValidationException|\TypeError) {
            return null;
        }
    }

    private function validated(array $data): array
    {
        $result = Validator::make($data, ['minimum_usd' => 'required|string', 'fx_rate_idr' => 'required|string', 'enabled' => 'required|boolean'])->validate();
        $result['minimum_usd'] = Finance::decimal($result['minimum_usd'], 4, 16, true);
        $result['fx_rate_idr'] = Finance::decimal($result['fx_rate_idr'], 6, 14, true);

        return $result;
    }

    public function save(array $input): void
    {
        $admin = Finance::admin();
        $data = $this->validated($input);
        DB::transaction(function () use ($data, $admin) {
            $row = Setting::where('key', 'rebate.payout')->lockForUpdate()->first() ?? new Setting;
            $row->forceFill(['key' => 'rebate.payout', 'value_encrypted' => json_encode($data, JSON_THROW_ON_ERROR), 'is_secret' => true])->save();
            Audit::record($admin, 'rebate.payout_settings', $row, ['content_changed' => true]);
        }, 3);
    }
}
