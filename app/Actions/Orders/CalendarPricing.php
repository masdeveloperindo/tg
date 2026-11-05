<?php

namespace App\Actions\Orders;

use App\Models\License;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CalendarPricing
{
    public function rules(array $rules): array
    {
        $data = Validator::make($rules, [
            'monthly_discount_pct' => ['required', 'string', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/'],
            'rounding_increment_idr' => ['required', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'rounding_mode' => ['required', Rule::in(['nearest', 'up', 'down'])],
            'quote_minutes' => 'required|integer|min:1|max:60', 'payment_minutes' => 'required|integer|min:1|max:10080',
        ])->validate();
        if (bccomp($data['monthly_discount_pct'], '100', 4) > 0 || bccomp($data['rounding_increment_idr'], '0', 2) <= 0) {
            throw ValidationException::withMessages(['pricing' => 'Aturan harga tidak valid.']);
        }

        return $data;
    }

    public static function end(int $year): CarbonImmutable
    {
        return CarbonImmutable::create($year + 1, 1, 1, 0, 0, 0, 'Asia/Jakarta')->utc();
    }

    public function calculate(Product $product, array $rules, ?CarbonImmutable $at = null, ?License $renewal = null): array
    {
        $rules = $this->rules($rules);
        $at = ($at ?? CarbonImmutable::now())->setTimezone('Asia/Jakarta');
        $year = $at->year;
        $month = $at->month;
        if ($renewal) {
            if ($renewal->status->value !== 'active' || $renewal->product_id !== $product->id || ! $renewal->expires_at->equalTo(self::end($renewal->coverage_year))) {
                throw ValidationException::withMessages(['renewal' => 'Lisensi perlu diperiksa admin sebelum diperpanjang.']);
            }
            if ($renewal->expires_at->isAfter($at)) {
                $opens = CarbonImmutable::instance($renewal->expires_at)->setTimezone('Asia/Jakarta')->subDay()->startOfDay()->subDays(30);
                if ($at->isBefore($opens)) {
                    throw ValidationException::withMessages(['renewal' => 'Perpanjangan tersedia mulai H-30 sebelum akhir masa akses.']);
                }
                $year = $renewal->coverage_year + 1;
                $month = 1;
            }
        }
        $base = $product->base_price_idr;
        $minimum = $product->minimum_price_idr;
        if (bccomp($base, '0', 2) < 0 || bccomp($minimum, $base, 2) > 0) {
            throw ValidationException::withMessages(['pricing' => 'Harga produk belum valid.']);
        }
        $factor = bcsub('1', bcdiv(bcmul($rules['monthly_discount_pct'], (string) ($month - 1), 6), '100', 8), 8);
        $raw = bcmul($base, $factor, 8);
        if (bccomp($raw, $minimum, 8) < 0) {
            $raw = $minimum;
        }
        $step = $rules['rounding_increment_idr'];
        $whole = bcdiv($raw, $step, 0);
        $remainder = bcsub($raw, bcmul($whole, $step, 8), 8);
        if (($rules['rounding_mode'] === 'up' && bccomp($remainder, '0', 8) > 0) || ($rules['rounding_mode'] === 'nearest' && bccomp(bcmul($remainder, '2', 8), $step, 8) >= 0)) {
            $whole = bcadd($whole, '1', 0);
        }
        $amount = bcmul($whole, $step, 2);
        if (bccomp($amount, $minimum, 2) < 0) {
            $whole = bcdiv($minimum, $step, 0);
            if (bccomp(bcmul($whole, $step, 2), $minimum, 2) < 0) {
                $whole = bcadd($whole, '1', 0);
            }$amount = bcmul($whole, $step, 2);
        }
        if (bccomp($amount, '999999999999999999.99', 2) > 0) {
            throw ValidationException::withMessages(['pricing' => 'Hasil pembulatan melebihi batas harga.']);
        }

        return ['amount_idr' => $amount, 'coverage_year' => $year, 'coverage_expires_at' => self::end($year), 'pricing_month' => $month, 'rules' => $rules];
    }

    public function simulate(Product $product, array $rules, int $year): array
    {
        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $result[$month] = $this->calculate($product, $rules, CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta'));
        }

        return $result;
    }
}
