<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\Marketplace\CreateOrder;
use App\Actions\Marketplace\FulfillOrder;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Checkout;

class CheckoutController extends Controller
{
    /**
     * Start a purchase. Free products are granted straight away; paid
     * ones go to Stripe Checkout and are only granted when the
     * webhook confirms payment.
     */
    public function store(
        Request $request,
        Product $product,
        CreateOrder $createOrder,
        FulfillOrder $fulfillOrder,
    ): Checkout|RedirectResponse {
        $team = app(CurrentTeam::class)->model();

        abort_if($team === null, 403);
        abort_unless($product->isPublished(), 404);

        try {
            $order = $createOrder->handle($team, $request->user(), $product);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($product->isFree()) {
            $fulfillOrder->handle($order);

            return redirect()
                ->route('purchases.index')
                ->with('status', __('":product" is ready to download.', ['product' => $product->name]));
        }

        $checkout = $team->checkoutCharge(
            amount: $product->price,
            name: $product->name,
            quantity: 1,
            sessionOptions: [
                'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('marketplace.show', $product),
                // The webhook reads this back to find the local order.
                'metadata' => ['order_id' => $order->id],
                'client_reference_id' => (string) $order->id,
            ],
        );

        $order->forceFill(['stripe_checkout_session_id' => $checkout->asStripeCheckoutSession()->id])->save();

        return $checkout;
    }

    /**
     * Where Stripe returns the buyer. Fulfillment is the webhook's job,
     * so this only reassures the buyer — it never grants anything.
     */
    public function success(): RedirectResponse
    {
        return redirect()
            ->route('purchases.index')
            ->with('status', __('Payment received. Your files appear here once Stripe confirms the charge.'));
    }
}
