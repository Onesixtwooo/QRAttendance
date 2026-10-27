<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\EventSchedule;
use App\Models\Student;
use App\Support\StudentQrCode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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

        $student = $qrIdentity['student_qr_token']
            ? Student::query()->where('qr_token', $qrIdentity['student_qr_token'])->first()
            : ($qrIdentity['student_record_id']
            ? Student::query()->find($qrIdentity['student_record_id'])
            : Student::query()->where('student_id', $qrIdentity['student_id'])->first());

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

        $approvalToken = (string) Str::uuid();
        Cache::put('attendance-approval:'.$student->id.':'.$approvalToken, [
            'event_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scan_type' => $scanType,
            'verification_code' => $qrIdentity['verification_code'],
        ], now()->addMinutes(2));
        Cache::put('attendance-approval:'.$student->id.':current', $approvalToken, now()->addMinutes(2));

        return response()->json([
            'message' => 'Scan received. The student must approve it from their portal.',
            'pending_confirmation' => true,
            'student' => trim($student->first_name.' '.$student->last_name),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $data = $request->validate([
            'token' => ['required', 'uuid'],
        ]);
        $student = $request->user()->student;
        abort_unless($student, 403);

        $key = 'attendance-approval:'.$student->id.':'.$data['token'];
        $pending = Cache::pull($key);
        $currentKey = 'attendance-approval:'.$student->id.':current';
        if (Cache::get($currentKey) === $data['token']) {
            Cache::forget($currentKey);
        }

        if (! is_array($pending) || (int) $pending['student_id'] !== (int) $student->id) {
            return response()->json(['message' => 'This attendance request has expired.'], 422);
        }

        $event = EventSchedule::query()->find($pending['event_id']);
        $booth = Booth::query()->find($pending['booth_id']);

        if (! $event || ! $booth || $event->status !== 'In Progress') {
            return response()->json(['message' => 'This attendance event is no longer available.'], 422);
        }

        $scanDecision = $this->nextScanDecision($event, $student);

        if ($scanDecision['type'] !== $pending['scan_type']) {
            return response()->json([
                'message' => $scanDecision['message'] ?: 'This attendance request is no longer valid.',
            ], $scanDecision['status']);
        }

        try {
            $record = AttendanceRecord::create([
                'event_schedule_id' => $event->id,
                'booth_id' => $booth->id,
                'student_id' => $student->id,
                'scanned_at' => now(),
                'scan_type' => $pending['scan_type'],
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'This student already has a '.$pending['scan_type'].' scan for the current event.',
            ], 409);
        }

        return response()->json([
            'message' => match ($record->scan_type) {
                AttendanceRecord::TYPE_TIME_OUT => 'Time-out scan approved.',
                AttendanceRecord::TYPE_VERIFICATION => 'Verification scan approved.',
                default => 'Time-in scan approved.',
            },
            'record' => [
                'event' => $event->title,
                'scan_type' => $record->scan_type,
                'scan_type_label' => match ($record->scan_type) {
                    AttendanceRecord::TYPE_TIME_OUT => 'Time-out',
                    AttendanceRecord::TYPE_VERIFICATION => 'Verification',
                    default => 'Time-in',
                },
                'student' => trim($student->first_name.' '.$student->last_name),
                'student_id' => $student->student_id,
                'verification_code' => $pending['verification_code'],
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
            'student_qr_token' => trim((string) ($decoded['student_qr_token'] ?? '')),
            'student_record_id' => filter_var($decoded['student_record_id'] ?? null, FILTER_VALIDATE_INT),
            'verification_code' => strtoupper(substr((string) $decoded['signature'], 0, 8)),
        ];
    }

    private function currentEvent(): ?EventSchedule
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return EventSchedule::query()
            ->where('status', 'In Progress')
            ->where(function ($query) use ($now): void {
                $query->where('override_schedule_time', true)
                    ->orWhere(function ($scheduledEvent) use ($now): void {
                        $scheduledEvent->whereDate('event_date', $now->toDateString())
                            ->whereTime('end_time', '>=', $now->format('H:i:s'));
                    });
            })
            ->orderByDesc('event_date')
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
