<?php

namespace App\Http\Controllers;

use App\Mail\AdminAccountCredentials;
use App\Models\Booth;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->where('role', 'admin')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => ucfirst($user->role),
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->format('M j, Y'),
            ]);

        $editUser = null;

        if ($request->query('edit')) {
            $editUser = User::query()
                ->where('role', 'admin')
                ->find($request->query('edit'));
        }

        return view('welcome', [
            'page' => 'users',
            'users' => $users,
            'editUser' => $editUser,
            'userCounts' => [
                'admin' => User::where('role', 'admin')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        $temporaryPassword = str()->random(12);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $temporaryPassword,
            'role' => 'admin',
        ]);

        try {
            Mail::to($user->email)->send(new AdminAccountCredentials($user, $temporaryPassword));
            $status = config('mail.default') === 'log'
                ? 'Admin account created. Credentials were written to the mail log, not emailed.'
                : 'Admin account created. Login credentials were submitted to the configured mail transport for '.$user->email.'.';
        } catch (Throwable $exception) {
            report($exception);
            $status = 'Admin account created, but the login email could not be sent. Check the mail settings, then use Resend credentials beside the account.';
        }

        return redirect()
            ->route('users')
            ->with('user_status', $status);
    }

    public function resendCredentials(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($user->role === 'admin', 404);

        if (config('mail.default') === 'log') {
            return redirect()->route('users')->with('user_status', 'Mail is configured for logging. Configure an email transport before resending admin credentials.');
        }

        $temporaryPassword = str()->random(12);
        $previousPassword = $user->password;
        $user->update(['password' => $temporaryPassword]);

        try {
            Mail::to($user->email)->send(new AdminAccountCredentials($user, $temporaryPassword));
            $status = 'New admin credentials submitted to the configured mail transport for '.$user->email.'.';
        } catch (Throwable $exception) {
            report($exception);
            $user->update(['password' => $previousPassword]);
            $status = 'Admin credentials could not be sent to '.$user->email.'. The existing password still works. Check the mail settings and try again.';
        }

        return redirect()->route('users')->with('user_status', $status);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'admin',
            'is_active' => $data['is_active'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        Booth::where('user_id', $user->id)->update(['user_id' => null]);

        return redirect()
            ->route('users')
            ->with('user_status', 'User account updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Booth::where('user_id', $user->id)->update(['user_id' => null]);

        $user->delete();

        return redirect()
            ->route('users')
            ->with('user_status', 'User account deleted.');
    }
}
