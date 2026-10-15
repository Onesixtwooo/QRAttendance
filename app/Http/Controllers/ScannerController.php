<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\EventSchedule;
use App\Models\Student;
use App\Support\StudentQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function index(): View
    {
        request()->user()?->booth?->markSeen();

        return view('welcome', [
            'page' => 'scanner',
            'scannerBooths' => Booth::query()->orderBy('name')->get(),
            'scannerCurrentEvent' => $this->currentEvent(),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        if (! $request->user()) {
            abort(403);
        }

        $data = $request->validate([
            'payload' => ['required', 'string'],
            'booth_id' => ['nullable', 'exists:booths,id'],
        ]);

        $qrIdentity = $this->studentCodeFromPayload($data['payload']);

        if ($qrIdentity === null) {
            return response()->json([
                'message' => 'This student QR code is invalid or has expired. Ask the student to refresh it.',
            ], 422);
        }

        $studentCode = $qrIdentity['student_id'];
        $student = Student::query()->where('student_id', $studentCode)->first();

        if (! $student) {
            return response()->json([
                'message' => 'Student QR was not found.',
            ], 404);
        }

        $event = $this->currentEvent();

        if (! $event) {
            return response()->json([
                'message' => 'No event is currently in progress.',
            ], 422);
        }

        $booth = $this->boothForScan($request);

        if (! $booth) {
            return response()->json([
                'message' => 'Select a booth before scanning.',
            ], 422);
        }

        $booth->markSeen();
        $scanDecision = $this->nextScanDecision($event, $student);

        if (! $scanDecision['type']) {
            return response()->json([
                'message' => $scanDecision['message'],
            ], $scanDecision['status']);
        }

        $scanType = $scanDecision['type'];

        $record = AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => now(),
            'scan_type' => $scanType,
        ]);

        return response()->json([
            'message' => match ($scanType) {
                AttendanceRecord::TYPE_TIME_OUT => 'Time-out scan saved.',
                AttendanceRecord::TYPE_VERIFICATION => 'Verification scan saved.',
                default => 'Time-in scan saved.',
            },
            'record' => [
                'id' => $record->id,
                'event' => $event->title,
                'scan_type' => $record->scan_type,
                'scan_type_label' => match ($record->scan_type) {
                    AttendanceRecord::TYPE_TIME_OUT => 'Time-out',
                    AttendanceRecord::TYPE_VERIFICATION => 'Verification',
                    default => 'Time-in',
                },
                'student' => trim($student->first_name.' '.$student->last_name),
                'student_id' => $student->student_id,
                'verification_code' => $qrIdentity['verification_code'],
                'course' => $student->course,
                'block' => $student->block,
                'year_level' => $student->year_level,
                'booth' => $booth->name,
                'time' => $record->scanned_at->format('g:i A'),
            ],
        ]);
    }

    private function studentCodeFromPayload(string $payload): ?array
    {
        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        if (($decoded['type'] ?? null) !== 'student_dynamic' || ! app(StudentQrCode::class)->valid($decoded)) {
            return null;
        }

        return [
            'student_id' => trim((string) $decoded['student_id']),
            'verification_code' => strtoupper(substr((string) $decoded['signature'], 0, 8)),
        ];
    }

    private function currentEvent(): ?EventSchedule
    {
        return EventSchedule::query()
            ->whereDate('event_date', now()->toDateString())
            ->where('status', 'In Progress')
            ->orderByDesc('start_time')
            ->first();
    }

    private function nextScanDecision(EventSchedule $event, Student $student): array
    {
        $existingTypes = AttendanceRecord::query()
            ->where('event_schedule_id', $event->id)
            ->where('student_id', $student->id)
            ->pluck('scan_type');

        if (($event->scan_phase ?? EventSchedule::PHASE_TIME_IN) === EventSchedule::PHASE_TIME_IN) {
            if (! ($event->time_in_open ?? true)) {
                return [
                    'type' => null,
                    'message' => 'Time-in scanning is currently closed for this event.',
                    'status' => 423,
                ];
            }

            if ($existingTypes->contains(AttendanceRecord::TYPE_TIME_IN)) {
                return [
                    'type' => null,
                    'message' => 'This student already has a time-in scan for the current event.',
                    'status' => 409,
                ];
            }

            return [
                'type' => AttendanceRecord::TYPE_TIME_IN,
                'message' => null,
                'status' => 200,
            ];
        }

        if (! $existingTypes->contains(AttendanceRecord::TYPE_TIME_IN)) {
            return [
                'type' => null,
                'message' => 'This student needs a time-in scan before time-out.',
                'status' => 422,
            ];
        }

        $requiredType = AttendanceRecord::TYPE_TIME_OUT;

        if ($existingTypes->contains($requiredType)) {
            return [
                'type' => null,
                    'message' => 'This student already has a '.$requiredType.' scan for the current event.',
                'status' => 409,
            ];
        }

        return [
            'type' => $requiredType,
            'message' => null,
            'status' => 200,
        ];
    }

    private function boothForScan(Request $request): ?Booth
    {
        $user = $request->user();

        if ($user?->role === 'booth') {
            return $user->booth;
        }

        if ($request->filled('booth_id')) {
            return Booth::find($request->input('booth_id'));
        }

        return null;
    }
}
