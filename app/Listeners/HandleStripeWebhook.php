<?php

namespace App\Listeners;

use App\Features\Api;
use App\Features\AuditLog;
use App\Features\Sso;
use App\Mail\PaymentFailedMail;
use App\Models\Team;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Pennant\Feature;

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
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->forgetFeatureFlags($event->payload),
            default => null,
        };
    }

    /**
     * Plan-driven feature flags are cached by Pennant; a subscription
     * change invalidates them so the next check re-resolves from the
     * new plan.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function forgetFeatureFlags(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! is_string($customerId)) {
            return;
        }

        $team = Team::where('stripe_id', $customerId)->first();

        if ($team === null) {
            return;
        }

        Feature::for($team)->forget([Api::class, AuditLog::class, Sso::class]);
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
