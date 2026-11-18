<?php

namespace App\Http\Middleware;

use App\Services\PhoneNumbers;
use Closure;
use Illuminate\Http\Request;

class NormalizePhoneNumbers
{
    public function handle(Request $request, Closure $next)
    {
        // Do not rewrite search URLs. Invalid values are retained for field-level validation.
        if (!$request->isMethodSafe()) $request->merge(app(PhoneNumbers::class)->normalizeFields($request->input()));
        return $next($request);
    }
}
