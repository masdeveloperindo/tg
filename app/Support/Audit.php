<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use InvalidArgumentException;

final class Audit
{
    public static function record(User $actor, string $action, User $subject, array $summary): void
    {
        $schemas = [
            'auth.registered' => ['role', 'status'],
            'auth.password_changed' => ['must_change_password', 'sessions_revoked'],
            'auth.client_password_reset' => ['must_change_password', 'contact_checked', 'identity_checked', 'reason_provided', 'sessions_revoked'],
            'auth.first_admin_created' => ['role', 'totp_required'],
        ];
        if (! isset($schemas[$action])) {
            throw new InvalidArgumentException('Unsupported audit action.');
        }
        $safe = ['result' => 'succeeded'];
        foreach ($schemas[$action] as $key) {
            $value = $summary[$key] ?? null;
            $valid = match ($key) {
                'role' => in_array($value, ['client', 'admin'], true),
                'status' => in_array($value, ['active', 'disabled'], true),
                'sessions_revoked' => is_int($value) && $value >= 0,
                default => is_bool($value),
            };
            if ($valid) {
                $safe[$key] = $value;
            }
        }
        (new ActivityLog)->forceFill([
            'actor_id' => $actor->getKey(), 'action' => $action,
            'subject_type' => User::class, 'subject_id' => $subject->getKey(),
            'request_id' => RequestId::current(), 'summary_json' => $safe,
        ])->save();
    }
}
