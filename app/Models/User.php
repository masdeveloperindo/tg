<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Concerns\StoresUtcDatetimes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use StoresUtcDatetimes;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['name', 'email', 'password'];

    protected $attributes = [
        'role' => 'client',
        'status' => 'active',
        'must_change_password' => false,
    ];

    protected $hidden = ['password', 'contact_value_enc', 'totp_secret_enc'];

    // The schema has no remember_token; persistent login is not enabled here.
    protected $rememberTokenName = null;

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value), 'UTF-8'),
        );
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'contact_channel' => ContactChannel::class,
            'contact_value_enc' => 'encrypted',
            'totp_secret_enc' => 'encrypted',
            'must_change_password' => 'boolean',
            'totp_enabled_at' => 'immutable_datetime',
            'admin_last_reauth_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(AdminRecoveryCode::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function tradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }
}
