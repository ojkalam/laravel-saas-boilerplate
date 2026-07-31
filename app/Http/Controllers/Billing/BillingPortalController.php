<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BillingPortalController extends Controller
{
    /**
     * Plan changes, card updates, cancellations, and invoices all live
     * in the Stripe billing portal — building a custom UI for them is
     * deliberately out of scope.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $team = app(CurrentTeam::class)->model();
        abort_if($team === null, 403);

        Gate::authorize('manageBilling', $team);

        return $team->redirectToBillingPortal(route('dashboard'));
    }
}
