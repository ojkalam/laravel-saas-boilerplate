<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-reverts impersonation sessions that have outlived their
 * time box, so a forgotten impersonation cannot linger.
 */
class ExpireImpersonation
{
    public function __construct(protected Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->active() && $this->impersonation->expired()) {
            $this->impersonation->stop('impersonation.expired');

            return redirect()->to('/admin');
        }

        return $next($request);
    }
}
