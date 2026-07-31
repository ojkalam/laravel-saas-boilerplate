<?php

namespace App\Listeners;

use App\Actions\Marketplace\FulfillOrder;
use App\Actions\Marketplace\RefundOrder;
use App\Features\Api;
use App\Features\AuditLog;
use App\Features\Sso;
use App\Mail\PaymentFailedMail;
use App\Models\Order;
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
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->payload),
            'charge.refunded' => $this->handleChargeRefunded($event->payload),
            default => null,
        };
    }

    /**
     * Marketplace fulfillment. This is the only place a purchase turns
     * into licenses — the browser redirect back from Stripe grants
     * nothing, because it can be forged or simply never arrive.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutCompleted(array $payload): void
    {
        $session = $payload['data']['object'] ?? [];

        // Subscription checkouts are Cashier's business, not ours.
        if (($session['mode'] ?? null) === 'subscription') {
            return;
        }

        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $order = $this->resolveOrder($session);

        if ($order === null || ! $order->isPending()) {
            return;
        }

        app(FulfillOrder::class)->handle($order);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeRefunded(array $payload): void
    {
        $charge = $payload['data']['object'] ?? [];
        $orderId = $charge['metadata']['order_id'] ?? null;

        if (! is_numeric($orderId)) {
            return;
        }

        $order = Order::acrossTeams()->find((int) $orderId);

        if ($order === null || ! $order->isPaid()) {
            return;
        }

        app(RefundOrder::class)->handle($order);
    }

    /**
     * Prefer the metadata we set at checkout; fall back to the session
     * id we stored on the order.
     *
     * @param  array<string, mixed>  $session
     */
    protected function resolveOrder(array $session): ?Order
    {
        $orderId = $session['metadata']['order_id'] ?? $session['client_reference_id'] ?? null;

        if (is_numeric($orderId)) {
            return Order::acrossTeams()->find((int) $orderId);
        }

        $sessionId = $session['id'] ?? null;

        return is_string($sessionId)
            ? Order::acrossTeams()->where('stripe_checkout_session_id', $sessionId)->first()
            : null;
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
