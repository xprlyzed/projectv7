<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            Cache::put('user-is-online-' . $user->id, true, now()->addMinutes(5));
            Cache::put('user-last-seen-' . $user->id, now()->toIso8601String(), now()->addDays(30));
        }

        return $next($request);
    }
}
