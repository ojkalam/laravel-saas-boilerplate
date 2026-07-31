<?php

use App\Listeners\HandleStripeWebhook;
use App\Mail\PaymentFailedMail;
use App\Models\Team;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Events\WebhookReceived;

test('a failed invoice payment emails the team owner', function () {
    Mail::fake();

    $team = Team::factory()->create(['stripe_id' => 'cus_webhook_test']);

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['customer' => 'cus_webhook_test']],
    ]));

    Mail::assertSent(PaymentFailedMail::class, fn (PaymentFailedMail $mail) => $mail->hasTo($team->owner->email) && $mail->team->is($team));
});

test('webhooks for unknown customers are ignored', function () {
    Mail::fake();

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['customer' => 'cus_missing']],
    ]));

    Mail::assertNothingSent();
});

test('unrelated webhook events are ignored', function () {
    Mail::fake();

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => ['customer' => 'cus_webhook_test']],
    ]));

    Mail::assertNothingSent();
});

test('the cashier webhook endpoint is registered', function () {
    expect(collect(app('router')->getRoutes())->contains(
        fn ($route) => $route->uri() === 'stripe/webhook',
    ))->toBeTrue();
});
