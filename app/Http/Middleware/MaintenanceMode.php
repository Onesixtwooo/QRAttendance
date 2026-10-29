<?php

namespace App\Http\Middleware;

use App\Models\ApplicationSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = Schema::hasTable('application_settings')
            && ApplicationSetting::query()->whereKey('maintenance_mode')->value('value') === '1';
        $isAdmin = $request->user()?->role === 'admin';

        if ($enabled && ! $isAdmin && ! $request->is('login')) {
            return response()->view('maintenance', status: 503);
        }

        return $next($request);
    }
}
