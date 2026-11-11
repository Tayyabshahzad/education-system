<?php

namespace App\Http\Middleware;

use App\Support\Identity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Normalises CNIC and phone on every form submission so uniqueness checks are reliable. */
class NormalizeIdentityFields
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            Identity::normalize($request);
        }

        return $next($request);
    }
}
