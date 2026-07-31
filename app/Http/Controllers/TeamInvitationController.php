<?php

namespace App\Http\Controllers;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamInvitationController extends Controller
{
    public function accept(
        Request $request,
        TeamInvitation $invitation,
        AcceptTeamInvitation $acceptTeamInvitation,
    ): RedirectResponse {
        try {
            $acceptTeamInvitation->handle($request->user(), $invitation);
        } catch (ValidationException $e) {
            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard')->with(
            'status',
            __('You have joined the :team team.', ['team' => $invitation->team->name]),
        );
    }
}
