<?php

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Middleware\SetTeamFromApiToken;
use App\Support\CurrentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
