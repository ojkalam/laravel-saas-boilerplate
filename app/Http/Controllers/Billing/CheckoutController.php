<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Support\CurrentTeam;
use App\Support\Plans\PlanRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Checkout;

class CheckoutController extends Controller
{
    /**
     * Send the current team to Stripe Checkout for the given plan.
     * Plan changes after the first subscription go through the billing
     * portal instead.
     */
    public function __invoke(Request $request, string $plan, string $interval, PlanRegistry $plans): Checkout|RedirectResponse
    {
        $team = app(CurrentTeam::class)->model();
        abort_if($team === null, 403);

        Gate::authorize('manageBilling', $team);

        abort_unless(in_array($interval, ['monthly', 'yearly'], true), 404);

        $price = $plans->find($plan)->stripePriceFor($interval);
        abort_if($price === null, 404);

        if ($team->hasActiveSubscription()) {
            return redirect()->route('billing.portal');
        }

        $seats = $plans->find($plan)->perSeat ? max(1, $team->members()->count()) : 1;

        return $team->newSubscription('default', $price)
            ->quantity($seats)
            ->checkout([
                'success_url' => route('dashboard').'?checkout=success',
                'cancel_url' => route('pricing').'?checkout=cancelled',
            ]);
    }
}
