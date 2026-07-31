<?php

namespace App\Actions\Marketplace;

use App\Enums\LicenseStatus;
use App\Enums\OrderStatus;
use App\Models\License;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * A refunded purchase loses its entitlements: licenses are revoked, so
 * downloads and activations stop working.
 */
class RefundOrder
{
    public function handle(Order $order): Order
    {
        if ($order->isRefunded()) {
            return $order;
        }

        DB::transaction(function () use ($order): void {
            $order->forceFill([
                'status' => OrderStatus::Refunded,
                'refunded_at' => now(),
            ])->save();

            License::withoutGlobalScope('team')
                ->whereIn('order_item_id', $order->items()->select('id'))
                ->update(['status' => LicenseStatus::Revoked]);
        });

        activity()
            ->performedOn($order)
            ->withProperties(['number' => $order->number])
            ->log('order.refunded');

        return $order;
    }
}
