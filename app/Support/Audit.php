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
            'broker.saved' => ['rate_before', 'rate_after', 'effective_before', 'effective_after', 'content_changed'],
            'ib.account_attached' => ['proof_provided'],
            'ib.account_decided' => ['approved', 'proof_checked', 'reason_provided'],
            'rebate.credited' => ['resource_id'],
            'rebate.corrected' => ['resource_id'],
            'rebate.payout_settings' => ['content_changed'],
            'withdrawal.requested' => ['resource_id'],
            'withdrawal.cancelled' => [],
            'withdrawal.processing' => ['transfer_checked', 'reference_provided', 'reason_provided'],
            'withdrawal.paid' => ['transfer_checked', 'reference_provided', 'reason_provided'],
            'withdrawal.rejected' => ['transfer_checked', 'reference_provided', 'reason_provided'],
            'license.account_change_requested' => ['resource_id', 'intent_sha256', 'before_sha256'],
            'license.account_change_decided' => ['resource_id', 'approved', 'reason_provided', 'period_year'],
            'refund.initiated' => ['resource_id', 'reason_provided'],
            'refund.decided' => ['completed', 'reference_provided'],
            'order.payment_confirmed' => ['funds_checked', 'reference_provided', 'note_provided', 'resource_id'],
            'order.rejected' => ['reason_provided'],
            'license.issued' => ['coverage_year', 'resource_id'],
            'license.renewed' => ['coverage_year', 'resource_id'],
            'license.status_changed' => ['before_status', 'after_status', 'reason_code', 'intent_sha256'],
            'order.created' => ['coverage_year'],
            'product.created' => ['content_changed'],
            'product.updated' => ['content_changed'],
            'product.status_changed' => ['status'],
            'product.version_uploaded' => ['version_count'],
            'product.version_updated' => ['resource_id'],
            'product.version_activated' => ['version_count'],
            'product.media_changed' => ['media_count', 'resource_id'],
            'product.tutorial_changed' => ['step_count'],
            'product.faq_changed' => ['faq_count'],
            'product.performance_changed' => ['performance_count', 'resource_id', 'intent_sha256', 'before_sha256', 'after_sha256'],
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
                'rate_before', 'rate_after' => is_string($value) && preg_match('/\A[0-9]{1,14}\.[0-9]{4}\z/', $value) === 1,
                'effective_before', 'effective_after' => is_string($value) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) === 1,
                'intent_sha256', 'before_sha256', 'after_sha256' => is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1,
                'before_status','after_status' => in_array($value, ['active', 'suspended', 'revoked'], true),
                'reason_code' => in_array($value, ['security_review', 'client_request', 'policy_violation', 'manual_correction'], true),
                'role' => in_array($value, ['client', 'admin'], true),
                'status' => in_array($value, ['active', 'disabled', 'draft', 'published', 'archived'], true),
                'period_year', 'coverage_year', 'resource_id', 'step_count', 'sessions_revoked', 'version_count', 'media_count', 'faq_count', 'performance_count' => is_int($value) && $value >= 0,
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
