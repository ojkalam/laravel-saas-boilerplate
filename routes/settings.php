<?php

use App\Actions\Users\ExportUserData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/billing', 'pages::settings.billing')->name('billing.edit');

    Route::livewire('settings/team', 'pages::settings.team')->name('team.edit');

    Route::get('settings/profile/export', function (Request $request) {
        $data = app(ExportUserData::class)->handle($request->user());

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="account-export.json"',
        ], JSON_PRETTY_PRINT);
    })->name('profile.export');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
