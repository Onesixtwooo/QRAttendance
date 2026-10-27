<?php

namespace App\Http\Controllers;

use App\Mail\AdminAccountCredentials;
use App\Mail\StudentAccountCredentials;
use App\Models\EmailDeliveryFailure;
use App\Models\EmailDeliveryAttempt;
use App\Models\Student;
use App\Models\User;
use App\Support\EmailDeliveryFailureLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $logPath = storage_path('logs/laravel.log');
        $logTail = 'The application log does not exist yet.';

        if (is_readable($logPath)) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES);
            $logTail = implode(PHP_EOL, array_slice($lines ?: [], -250));
            if ($logTail === '') {
                $logTail = 'The application log is empty.';
            }
        }

        return view('welcome', [
            'page' => 'settings',
            'logTail' => $logTail,
            'logPath' => $logPath,
            'mailFailures' => EmailDeliveryFailure::query()
                ->where('status', 'pending')
                ->latest('last_attempted_at')
                ->get(),
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(50)->get(),
            'mailConfig' => [
                'mailer' => (string) config('mail.default'),
                'batch_limit' => (int) config('mail.batch_limit'),
                'from' => (string) config('mail.from.address'),
            ],
            'mailCounts' => [
                'sent_today' => EmailDeliveryAttempt::query()->where('status', 'sent')->whereDate('occurred_at', today())->count(),
                'failed_today' => EmailDeliveryAttempt::query()->where('status', 'failed')->whereDate('occurred_at', today())->count(),
            ],
        ]);
    }

    public function retryMail(Request $request, EmailDeliveryFailure $failure, EmailDeliveryFailureLogger $logger): RedirectResponse
    {
        $this->ensureAdmin($request);

        if ($failure->status !== 'pending') {
            return back()->with('settings_status', 'This email failure has already been resolved.');
        }

        if (config('mail.default') === 'log') {
            return back()->with('settings_status', 'Mail is configured for logging, so no email was sent. Configure SMTP first.');
        }

        try {
            if ($failure->kind === 'student_credentials') {
                $student = Student::with('user')->findOrFail($failure->related_id);
                $user = $student->user;
                abort_unless($user?->role === 'student', 404);
                $password = Str::random(12);
                $previousPassword = $user->password;
                $previousForceChange = $user->force_password_change;
                $user->update(['password' => $password, 'force_password_change' => true]);

                try {
                    Mail::to($user->email)->send(new StudentAccountCredentials($student->load('user'), $password));
                    app(EmailDeliveryFailureLogger::class)->sent($failure->kind, $user->email);
                } catch (Throwable $exception) {
                    $user->update(['password' => $previousPassword, 'force_password_change' => $previousForceChange]);
                    $logger->record($failure->kind, $user->email, $student, $exception);
                    throw $exception;
                }

                $logger->resolve($failure->kind, $student);
            } elseif ($failure->kind === 'admin_credentials') {
                $user = User::query()->where('role', 'admin')->findOrFail($failure->related_id);
                $password = Str::random(12);
                $previousPassword = $user->password;
                $user->update(['password' => $password]);

                try {
                    Mail::to($user->email)->send(new AdminAccountCredentials($user, $password));
                    app(EmailDeliveryFailureLogger::class)->sent($failure->kind, $user->email);
                } catch (Throwable $exception) {
                    $user->update(['password' => $previousPassword]);
                    $logger->record($failure->kind, $user->email, $user, $exception);
                    throw $exception;
                }

                $logger->resolve($failure->kind, $user);
            } else {
                return back()->with('settings_status', 'This email type cannot be retried from Settings.');
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('settings_status', 'Retry failed: '.$exception->getMessage());
        }

        return back()->with('settings_status', 'Email sent successfully to '.$failure->recipient.'.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}
