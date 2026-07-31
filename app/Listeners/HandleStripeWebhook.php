<?php

namespace App\Listeners;

use App\Mail\PaymentFailedMail;
use App\Models\Team;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Cashier keeps subscription state in sync on its own (including
 * customer.subscription.updated/deleted). This listener adds the
 * app-specific dunning behavior on top. Cashier's webhook controller
 * verifies signatures and Stripe retries failures, so keep handlers
 * idempotent.
 */
class HandleStripeWebhook
{
    public function handle(WebhookReceived $event): void
    {
        match ($event->payload['type'] ?? null) {
            'invoice.payment_failed' => $this->handlePaymentFailed($event->payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentFailed(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! is_string($customerId)) {
            return;
        }

        $team = Team::where('stripe_id', $customerId)->first();

        if ($team === null) {
            return;
        }

        Mail::to($team->owner->email)->send(new PaymentFailedMail($team));
    }
}
