<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ImpersonationController extends Controller
{
    public function store(Request $request, User $user, Impersonation $impersonation): RedirectResponse
    {
        abort_unless($request->user()->is_staff, 403);

        try {
            $impersonation->start($request->user(), $user);
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return redirect()->route('dashboard');
    }

    public function destroy(Impersonation $impersonation): RedirectResponse
    {
        abort_unless($impersonation->active(), 403);

        $impersonation->stop();

        return redirect()->to('/admin');
    }
}
