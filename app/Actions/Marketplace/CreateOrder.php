<?php

namespace App\Actions\Marketplace;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the intent to buy. Nothing is granted here — fulfillment
 * happens only once payment is confirmed (or immediately, for free
 * products).
 */
class CreateOrder
{
    public function handle(Team $team, User $user, Product $product): Order
    {
        if (! $product->isPurchasable()) {
            throw ValidationException::withMessages([
                'product' => __('This product is not available for purchase.'),
            ]);
        }

        return DB::transaction(function () use ($team, $user, $product): Order {
            $order = new Order([
                'user_id' => $user->id,
                'number' => Order::generateNumber(),
                'status' => OrderStatus::Pending,
                'currency' => 'usd',
            ]);

            $order->team()->associate($team);
            $order->total = $product->price;
            $order->save();

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
            ]);

            return $order->load('items');
        });
    }
}
