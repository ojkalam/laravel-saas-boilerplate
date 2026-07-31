<?php

use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Middleware\SetTeamFromApiToken;
use App\Support\CurrentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| License endpoints for installed themes and apps. No session or token:
| the license key is the credential, so these are throttled tightly.
*/
Route::prefix('v1/license')
    ->middleware('throttle:30,1')
    ->name('api.license.')
    ->group(function () {
        Route::post('activate', [LicenseController::class, 'activate'])->name('activate');
        Route::post('deactivate', [LicenseController::class, 'deactivate'])->name('deactivate');
        Route::get('check', [LicenseController::class, 'check'])->name('check');
        Route::get('latest-version', [LicenseController::class, 'latestVersion'])->name('latest-version');
        Route::get('download', [LicenseController::class, 'download'])->name('download');
    });

Route::prefix('v1')
    ->middleware(['auth:sanctum', SetTeamFromApiToken::class, 'throttle:api'])
    ->group(function () {
        Route::get('me', function (Request $request) {
            $team = app(CurrentTeam::class)->model();

            return response()->json([
                'data' => [
                    'user' => $request->user()->only(['id', 'name', 'email']),
                    'team' => $team->only(['id', 'name', 'slug']),
                    'plan' => $team->plan()->key,
                ],
            ]);
        });

        Route::apiResource('projects', ProjectController::class);
    });
