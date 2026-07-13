<?php

namespace App\Http\Controllers;

use App\Models\Booth;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoothController extends Controller
{
    public function index(Request $request): View
    {
        $booths = Booth::query()
            ->with('user')
            ->withCount([
                'attendanceRecords as scans_today_count' => fn ($query) => $query->whereDate('scanned_at', today()),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Booth $booth): array => $this->formatBooth($booth));

        $editBooth = null;

        if ($request->query('edit')) {
            $editBooth = Booth::with('user')->find($request->query('edit'));
        }

        return view('welcome', [
            'page' => 'booths',
            'booths' => $booths,
            'editBooth' => $editBooth,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['password'],
                'role' => 'booth',
            ]);

            Booth::create([
                'name' => $data['name'],
                'status' => 'Online',
                'user_id' => $user->id,
                'admin_name' => $user->name,
                'admin_email' => $user->email,
            ]);
        });

        return redirect()
            ->route('booths')
            ->with('booth_status', 'Booth and user-admin login saved.');
    }

    public function update(Request $request, Booth $booth): RedirectResponse
    {
        $data = $this->validated($request, $booth);

        DB::transaction(function () use ($booth, $data) {
            $user = $booth->user;

            if (! $user) {
                $user = User::create([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => $data['password'],
                    'role' => 'booth',
                ]);
            } else {
                $user->fill([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'role' => 'booth',
                ]);

                if (! empty($data['password'])) {
                    $user->password = $data['password'];
                }

                $user->save();
            }

            $booth->update([
                'name' => $data['name'],
                'user_id' => $user->id,
                'admin_name' => $user->name,
                'admin_email' => $user->email,
            ]);
        });

        return redirect()
            ->route('booths')
            ->with('booth_status', 'Booth updated.');
    }

    public function destroy(Booth $booth): RedirectResponse
    {
        $booth->delete();

        return redirect()
            ->route('booths')
            ->with('booth_status', 'Booth deleted.');
    }

    public function statuses(): JsonResponse
    {
        $booths = Booth::query()
            ->withCount([
                'attendanceRecords as scans_today_count' => fn ($query) => $query->whereDate('scanned_at', today()),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Booth $booth): array => [
                'id' => $booth->id,
                'status' => $this->connectionStatus($booth),
                'scans' => number_format($booth->scans_today_count ?? 0),
                'last_seen' => $booth->last_seen_at?->diffForHumans() ?? 'Never connected',
            ])
            ->values();

        return response()->json(['booths' => $booths]);
    }

    public function heartbeat(Request $request): Response
    {
        $user = $request->user();
        $booth = $user?->booth;

        if (! $user || $user->role !== 'booth' || ! $booth) {
            abort(403);
        }

        $booth->markSeen();

        return response()->noContent();
    }

    private function validated(Request $request, ?Booth $booth = null): array
    {
        $userId = $booth?->user_id;
        $passwordRules = $booth && $userId
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('booths', 'name')->ignore($booth),
            ],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => $passwordRules,
        ]);
    }

    private function formatBooth(Booth $booth): array
    {
        $status = $this->connectionStatus($booth);

        return [
            'id' => $booth->id,
            'name' => $booth->name,
            'status' => $status,
            'scans' => number_format($booth->scans_today_count ?? 0),
            'admin_name' => $booth->user?->name ?? $booth->admin_name,
            'admin_email' => $booth->user?->email ?? $booth->admin_email,
            'last_seen' => $booth->last_seen_at?->diffForHumans() ?? 'Never connected',
        ];
    }

    private function connectionStatus(Booth $booth): string
    {
        if ($booth->status === 'Review') {
            return 'Review';
        }

        return $booth->last_seen_at && $booth->last_seen_at->gt(now()->subMinutes(2))
            ? 'Online'
            : 'Offline';
    }
}
