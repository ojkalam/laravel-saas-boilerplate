<?php

use App\Actions\Marketplace\FulfillOrder;
use App\Actions\Marketplace\RefundOrder;
use App\Enums\LicenseStatus;
use App\Listeners\HandleStripeWebhook;
use App\Mail\OrderReceiptMail;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Events\WebhookReceived;
use Spatie\Activitylog\Models\Activity;

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

/**
 * A pending order for one paid product, as checkout would leave it.
 *
 * @return array{0: Order, 1: Product, 2: Team}
 */
function pendingOrder(int $price = 4900): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $product = Product::factory()->withVersion()->create(['price' => $price]);

    // team_id is not mass-assignable by design, so associate it the
    // way the real action does.
    $order = new Order([
        'user_id' => $user->id,
        'number' => Order::generateNumber(),
        'total' => $price,
        'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->lexify('??????????'),
    ]);
    $order->team()->associate($team);
    $order->save();

    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => $price,
    ]);

    return [$order->fresh(['items']), $product, $team];
}

function completedSessionPayload(Order $order, array $overrides = []): array
{
    return [
        'type' => 'checkout.session.completed',
        'data' => ['object' => array_merge([
            'id' => $order->stripe_checkout_session_id,
            'mode' => 'payment',
            'payment_status' => 'paid',
            'metadata' => ['order_id' => (string) $order->id],
        ], $overrides)],
    ];
}

test('a completed checkout webhook fulfills the order and issues a license', function () {
    Mail::fake();

    [$order, $product, $team] = pendingOrder();

    (new HandleStripeWebhook)->handle(new WebhookReceived(completedSessionPayload($order)));

    $order->refresh();
    app(CurrentTeam::class)->set($team);

    expect($order->isPaid())->toBeTrue()
        ->and($order->paid_at)->not->toBeNull()
        ->and(License::query()->count())->toBe(1);

    $license = License::query()->first();

    expect($license->product_id)->toBe($product->id)
        ->and($license->team_id)->toBe($team->id)
        ->and($license->order_item_id)->toBe($order->items->first()->id)
        ->and($license->isActive())->toBeTrue();

    Mail::assertSent(OrderReceiptMail::class, fn (OrderReceiptMail $mail) => $mail->hasTo($order->user->email));
});

test('replaying the same webhook does not issue a second license', function () {
    Mail::fake();

    [$order, , $team] = pendingOrder();
    $payload = completedSessionPayload($order);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));
    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));
    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    app(CurrentTeam::class)->set($team);

    expect(License::query()->count())->toBe(1);
    Mail::assertSentCount(1);
});

test('fulfilling an already paid order is a no-op', function () {
    Mail::fake();

    [$order, , $team] = pendingOrder();

    app(FulfillOrder::class)->handle($order);
    $firstPaidAt = $order->fresh()->paid_at;

    app(FulfillOrder::class)->handle($order->fresh());

    app(CurrentTeam::class)->set($team);

    expect(License::query()->count())->toBe(1)
        ->and($order->fresh()->paid_at->timestamp)->toBe($firstPaidAt->timestamp);
});

test('an unpaid session does not fulfill anything', function () {
    Mail::fake();

    [$order, , $team] = pendingOrder();

    (new HandleStripeWebhook)->handle(new WebhookReceived(
        completedSessionPayload($order, ['payment_status' => 'unpaid']),
    ));

    app(CurrentTeam::class)->set($team);

    expect($order->fresh()->isPending())->toBeTrue()
        ->and(License::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('subscription checkouts are left to cashier', function () {
    Mail::fake();

    [$order, , $team] = pendingOrder();

    (new HandleStripeWebhook)->handle(new WebhookReceived(
        completedSessionPayload($order, ['mode' => 'subscription']),
    ));

    app(CurrentTeam::class)->set($team);

    expect($order->fresh()->isPending())->toBeTrue()
        ->and(License::query()->count())->toBe(0);
});

test('a webhook for an unknown order is ignored', function () {
    Mail::fake();

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_unknown',
            'mode' => 'payment',
            'payment_status' => 'paid',
            'metadata' => ['order_id' => '999999'],
        ]],
    ]));

    expect(License::withoutGlobalScope('team')->count())->toBe(0);
    Mail::assertNothingSent();
});

test('an order can be resolved from the session id when metadata is missing', function () {
    Mail::fake();

    [$order, , $team] = pendingOrder();

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $order->stripe_checkout_session_id,
            'mode' => 'payment',
            'payment_status' => 'paid',
        ]],
    ]));

    app(CurrentTeam::class)->set($team);

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and(License::query()->count())->toBe(1);
});

test('fulfillment is written to the activity log', function () {
    Mail::fake();

    [$order] = pendingOrder();

    app(FulfillOrder::class)->handle($order);

    expect(Activity::forSubject($order)->where('description', 'order.fulfilled')->exists())->toBeTrue();
});

test('a refund revokes every license from the order', function () {
    Mail::fake();

    [$order, , $team] = pendingOrder();
    app(FulfillOrder::class)->handle($order);

    app(RefundOrder::class)->handle($order->fresh());

    app(CurrentTeam::class)->set($team);
    $license = License::query()->firstOrFail();

    expect($order->fresh()->isRefunded())->toBeTrue()
        ->and($order->fresh()->refunded_at)->not->toBeNull()
        ->and($license->status)->toBe(LicenseStatus::Revoked)
        ->and($license->isActive())->toBeFalse();
});

test('a refund webhook revokes licenses', function () {
    Mail::fake();

    [$order, , $team] = pendingOrder();
    app(FulfillOrder::class)->handle($order);

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'charge.refunded',
        'data' => ['object' => ['metadata' => ['order_id' => (string) $order->id]]],
    ]));

    app(CurrentTeam::class)->set($team);

    expect($order->fresh()->isRefunded())->toBeTrue()
        ->and(License::query()->first()->isRevoked())->toBeTrue();
});

test('refunding twice is a no-op', function () {
    Mail::fake();

    [$order] = pendingOrder();
    app(FulfillOrder::class)->handle($order);

    app(RefundOrder::class)->handle($order->fresh());
    $firstRefundedAt = $order->fresh()->refunded_at;

    app(RefundOrder::class)->handle($order->fresh());

    expect($order->fresh()->refunded_at->timestamp)->toBe($firstRefundedAt->timestamp);
});

test('a refund webhook for an unpaid order is ignored', function () {
    Mail::fake();

    [$order] = pendingOrder();

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'charge.refunded',
        'data' => ['object' => ['metadata' => ['order_id' => (string) $order->id]]],
    ]));

    expect($order->fresh()->isPending())->toBeTrue();
});

test('one team cannot see another team\'s orders or licenses', function () {
    Mail::fake();

    [$orderA, , $teamA] = pendingOrder();
    [$orderB, , $teamB] = pendingOrder();

    app(FulfillOrder::class)->handle($orderA);
    app(FulfillOrder::class)->handle($orderB);

    app(CurrentTeam::class)->set($teamA);

    expect(Order::query()->count())->toBe(1)
        ->and(Order::query()->first()->id)->toBe($orderA->id)
        ->and(License::query()->count())->toBe(1)
        ->and(License::query()->first()->team_id)->toBe($teamA->id);

    app(CurrentTeam::class)->set($teamB);

    expect(Order::query()->first()->id)->toBe($orderB->id);
});
