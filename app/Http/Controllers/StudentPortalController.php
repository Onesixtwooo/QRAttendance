<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Support\StudentQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function index(Request $request, StudentQrCode $qrCode): View
    {
        abort_unless($request->user()->role === 'student', 403);
        $student = $request->user()->student;
        abort_unless($student, 403, 'This account is not linked to a student record.');

        $records = $student->attendanceRecords()
            ->with('eventSchedule')
            ->whereNotNull('event_schedule_id')
            ->orderByDesc('scanned_at')
            ->get()
            ->groupBy('event_schedule_id');

        return view('students.portal', [
            'student' => $student,
            'qrCode' => $qrCode->generate($student),
            'events' => $records->map(function ($eventRecords) {
                $event = $eventRecords->first()->eventSchedule;

                return [
                    'title' => $event?->title ?? 'Event',
                    'date' => $event?->event_date?->format('M j, Y') ?? 'Date unavailable',
                    'venue' => $event?->venue,
                    'time_in' => $eventRecords->firstWhere('scan_type', AttendanceRecord::TYPE_TIME_IN)?->scanned_at?->format('g:i A'),
                    'verified' => $eventRecords->contains('scan_type', AttendanceRecord::TYPE_VERIFICATION),
                ];
            })->values(),
        ]);
    }

    public function qr(Request $request, StudentQrCode $qrCode): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);
        $student = $request->user()->student;
        abort_unless($student, 403);

        return response()->json($qrCode->generate($student));
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->email = $data['email'];

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('student.portal')->with('account_status', 'Your account was updated.');
    }
}
