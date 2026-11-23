<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\EventSchedule;
use Carbon\CarbonImmutable;
use App\Http\Middleware\RedirectBoothUsersToScanner;
use App\Http\Middleware\MaintenanceMode;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', RedirectBoothUsersToScanner::class);
        $middleware->appendToGroup('web', MaintenanceMode::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(function (): void {
            $now = CarbonImmutable::now(config('app.timezone'));

            EventSchedule::query()
                ->where('status', 'Upcoming')
                ->get()
                ->each(function (EventSchedule $event) use ($now): void {
                    $eventDate = CarbonImmutable::parse($event->event_date, config('app.timezone'));
                    $endsAt = CarbonImmutable::parse($eventDate->format('Y-m-d').' '.$event->end_time, config('app.timezone'));

                    if ($endsAt->greaterThanOrEqualTo($now) && $event->startsWithinAutomaticActivationWindow($now)) {
                        $event->forceFill(['status' => 'In Progress'])->saveQuietly();
                    }
                });

            EventSchedule::query()
                ->where('status', 'In Progress')
                ->get()
                ->each(function (EventSchedule $event) use ($now): void {
                    $event->syncAutomaticScanControls($now);
                });
        })->name('sync-event-schedule-statuses')->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
