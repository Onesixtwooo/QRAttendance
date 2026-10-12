<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectBoothUsersToScanner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            Auth::user()?->role === 'booth'
            && ! $request->routeIs('scanner', 'scanner.scan', 'logout', 'booths.heartbeat')
        ) {
            return redirect()->route('scanner');
        }

        if (
            Auth::user()?->role === 'student'
            && ! $request->routeIs('student.portal', 'student.qr', 'student.account.update', 'logout')
        ) {
            return redirect()->route('student.portal');
        }


        return $next($request);
    }
}
