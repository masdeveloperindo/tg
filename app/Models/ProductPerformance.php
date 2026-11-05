<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\PerformanceTestType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPerformance extends DomainModel
{
    protected $hidden = ['report_path'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'test_type' => PerformanceTestType::class,
            'account_environment' => AccountType::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'initial_capital' => 'decimal:2',
            'net_profit_pct' => 'decimal:4',
            'max_drawdown_pct' => 'decimal:4',
            'win_rate_pct' => 'decimal:4',
            'profit_factor' => 'decimal:6',
            'total_trades' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'version_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
