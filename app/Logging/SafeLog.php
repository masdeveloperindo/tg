<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Illuminate\Support\Str;
use Monolog\LogRecord;

final class SafeLog
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(self::sanitize(...));
    }

    public static function sanitize(LogRecord $record): LogRecord
    {
        // Allow metadata only: arbitrary messages, nested context, exceptions and extra are discarded.
        $context = [];
        $id = $record->context['request_id'] ?? null;
        if (is_string($id) && Str::isUuid($id)) {
            $context['request_id'] = $id;
        }
        foreach (['actor_id', 'subject_id', 'status'] as $key) {
            $value = $record->context[$key] ?? null;
            if (is_int($value) && $value >= 0) {
                $context[$key] = $value;
            }
        }
        $event = in_array($record->message, ['request.completed', 'request.failed', 'application.error'], true)
            ? $record->message : 'application.event';

        return $record->with(message: $event, context: $context, extra: []);
    }
}
