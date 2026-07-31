<?php

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Billing\BillingPortalController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\CurrentTeamController;
use App\Http\Controllers\TeamInvitationController;
use App\Support\Plans\PlanRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('health', function () {
    $checks = [
        'database' => rescue(fn () => DB::connection()->select('select 1') !== [], false, false),
        'redis' => rescue(fn () => Redis::connection()->ping() !== false, false, false),
    ];

    $healthy = ! in_array(false, $checks, true);

    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'checks' => $checks,
    ], $healthy ? 200 : 503);
})->name('health');

Route::get('pricing', function () {
    return view('pricing', [
        'plans' => app(PlanRegistry::class)->all(),
    ]);
})->name('pricing');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::put('current-team', [CurrentTeamController::class, 'update'])
        ->name('current-team.update');

    Route::get('team-invitations/{invitation}', [TeamInvitationController::class, 'accept'])
        ->middleware('signed')
        ->name('team-invitations.accept');

    Route::get('billing/checkout/{plan}/{interval}', CheckoutController::class)
        ->name('billing.checkout');

    Route::get('billing/portal', BillingPortalController::class)
        ->name('billing.portal');
});

Route::middleware('auth')->group(function () {
    Route::post('impersonation/{user}', [ImpersonationController::class, 'store'])
        ->name('impersonation.store');

    Route::delete('impersonation', [ImpersonationController::class, 'destroy'])
        ->name('impersonation.stop');
});

require __DIR__.'/settings.php';
