<?php

namespace App\Actions\Marketplace;

use App\Enums\LicenseStatus;
use App\Enums\OrderStatus;
use App\Mail\OrderReceiptMail;
use App\Models\License;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Turns a paid order into entitlements: one license per line item,
 * plus the receipt email.
 *
 * Stripe retries webhooks, so this must be safe to run twice. It is:
 * an already-paid order returns immediately, and license creation is
 * keyed off the order item.
 */
class FulfillOrder
{
    public function handle(Order $order): Order
    {
        if (! $order->isPending()) {
            return $order;
        }

        $licenses = DB::transaction(function () use ($order) {
            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ])->save();

            return $order->items()->with('product')->get()
                ->map(fn (OrderItem $item) => $this->issueLicense($order, $item))
                ->filter()
                ->values();
        });

        activity()
            ->performedOn($order)
            ->withProperties(['number' => $order->number, 'total' => $order->total])
            ->log('order.fulfilled');

        if ($order->user !== null) {
            Mail::to($order->user->email)->send(new OrderReceiptMail($order->fresh(['items'])));
        }

        $order->setRelation('licenses', $licenses);

        return $order;
    }

    protected function issueLicense(Order $order, OrderItem $item): ?License
    {
        if ($item->product_id === null) {
            return null;
        }

        // Re-delivery of the same webhook must not mint a second key.
        $existing = License::withoutGlobalScope('team')
            ->where('order_item_id', $item->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $license = new License([
            'product_id' => $item->product_id,
            'order_item_id' => $item->id,
            'key' => License::generateKey(),
            'status' => LicenseStatus::Active,
            'activation_limit' => (int) config('marketplace.licenses.activation_limit'),
            'expires_at' => now()->addMonths((int) config('marketplace.licenses.updates_months')),
        ]);

        $license->team()->associate($order->team_id);
        $license->save();

        return $license;
    }
}
