<?php

namespace App\Support;

use App\Models\EmailDeliveryFailure;
use App\Models\EmailDeliveryAttempt;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class EmailDeliveryFailureLogger
{
    public function record(string $kind, string $recipient, ?Model $related, Throwable $exception): EmailDeliveryFailure
    {
        EmailDeliveryAttempt::create([
            'kind' => $kind,
            'recipient' => $recipient,
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'occurred_at' => now(),
        ]);

        $failure = EmailDeliveryFailure::query()->firstOrNew([
            'kind' => $kind,
            'recipient' => $recipient,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'status' => 'pending',
        ]);
        $failure->error = $exception->getMessage();
        $failure->status = 'pending';
        $failure->attempts = ((int) $failure->attempts) + 1;
        $failure->last_attempted_at = now();
        $failure->resolved_at = null;
        $failure->save();

        return $failure;
    }

    public function sent(string $kind, string $recipient): void
    {
        EmailDeliveryAttempt::create([
            'kind' => $kind,
            'recipient' => $recipient,
            'status' => 'sent',
            'error' => null,
            'occurred_at' => now(),
        ]);
    }

    public function resolve(string $kind, Model $related): void
    {
        EmailDeliveryFailure::query()
            ->where('kind', $kind)
            ->where('related_type', $related->getMorphClass())
            ->where('related_id', $related->getKey())
            ->where('status', 'pending')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
