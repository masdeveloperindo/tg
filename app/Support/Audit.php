<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class Audit
{
    public static function record(User $actor, string $action, Model $subject, array $summary): void
    {
        $schemas = [
            'product.created' => ['content_changed'],
            'product.updated' => ['content_changed'],
            'product.status_changed' => ['status'],
            'product.version_uploaded' => ['version_count'],
            'product.version_updated' => ['resource_id'],
            'product.version_activated' => ['version_count'],
            'product.media_changed' => ['media_count', 'resource_id'],
            'product.tutorial_changed' => ['step_count'],
            'product.faq_changed' => ['faq_count'],
            'product.performance_changed' => ['performance_count', 'resource_id', 'before_sha256', 'after_sha256'],
            'product.content_removed' => ['content_changed', 'resource_id', 'before_sha256'],
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
                'before_sha256', 'after_sha256' => is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1,
                'role' => in_array($value, ['client', 'admin'], true),
                'status' => in_array($value, ['active', 'disabled', 'draft', 'published', 'archived'], true),
                'resource_id', 'step_count', 'sessions_revoked', 'version_count', 'media_count', 'faq_count', 'performance_count' => is_int($value) && $value >= 0,
                default => is_bool($value),
            };
            if ($valid) {
                $safe[$key] = $value;
            }
        }
        (new ActivityLog)->forceFill([
            'actor_id' => $actor->getKey(), 'action' => $action,
            'subject_type' => $subject::class, 'subject_id' => $subject->getKey(),
            'request_id' => RequestId::current(), 'summary_json' => $safe,
        ])->save();
    }
}
