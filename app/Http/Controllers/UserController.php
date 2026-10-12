<?php

namespace App\Http\Controllers;

use App\Models\Booth;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'admin',
        ]);

        return redirect()
            ->route('users')
            ->with('user_status', 'Admin account created.');
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
