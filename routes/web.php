<?php

use App\Http\Controllers\CurrentTeamController;
use App\Http\Controllers\TeamInvitationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::put('current-team', [CurrentTeamController::class, 'update'])
        ->name('current-team.update');

    Route::get('team-invitations/{invitation}', [TeamInvitationController::class, 'accept'])
        ->middleware('signed')
        ->name('team-invitations.accept');
});

require __DIR__.'/settings.php';
