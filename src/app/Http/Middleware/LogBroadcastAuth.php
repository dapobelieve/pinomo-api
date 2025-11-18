<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogBroadcastAuth
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->is('*/broadcasting/auth')) {
            Log::info('=== BROADCAST AUTH REQUEST ===', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'has_cookie_header' => $request->hasHeader('Cookie'),
                'input' => $request->all(),
                'auth_check' => auth()->check(),
                'auth_guard' => auth()->getDefaultDriver(),
                'auth_user' => auth()->user()?->id ?? 'null',
                'session_started' => $request->hasSession(),
                'session_id' => $request->hasSession() ? $request->session()->getId() : 'no session',
            ]);
        }

        return $response;
    }
}
