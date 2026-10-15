<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\EventSchedule;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceRecordController extends Controller
{
    public function index(Request $request): View
    {
        $filters = array_merge(
            ['date' => null, 'event_schedule_id' => null, 'course' => null, 'block' => null, 'year_level' => null],
            $request->only(['date', 'event_schedule_id', 'course', 'block', 'year_level'])
        );

        if (blank($filters['event_schedule_id'])) {
            $filters['event_schedule_id'] = $this->defaultEvent($filters)?->id;
        }

        $studentFiltersApplied = $this->studentFiltersApplied($filters);
        $records = $this->eventSummaries(
            $this->filteredRecords($filters)->get(),
            $this->studentCount($filters),
            $studentFiltersApplied
        );

        $editAttendanceRecord = null;

        if ($request->query('edit')) {
            $editAttendanceRecord = AttendanceRecord::with(['eventSchedule', 'booth', 'student'])->find($request->query('edit'));
        }

        return view('welcome', [
            'page' => 'records',
            'records' => $records,
            'recordFilters' => $filters,
            'recordFilterOptions' => $this->filterOptions(),
            'editAttendanceRecord' => $editAttendanceRecord,
        ]);
    }

    public function print(Request $request): View
    {
        $filters = array_merge(
            ['date' => null, 'event_schedule_id' => null, 'course' => null, 'block' => null, 'year_level' => null],
            $request->only(['date', 'event_schedule_id', 'course', 'block', 'year_level'])
        );

        if (blank($filters['event_schedule_id'])) {
            $filters['event_schedule_id'] = $this->defaultEvent($filters)?->id;
        }

        return $this->printWithFilters($filters);
    }

    public function printForEvent(EventSchedule $event): View
    {
        return $this->printWithFilters([
            'date' => null,
            'event_schedule_id' => $event->id,
            'course' => null,
            'block' => null,
            'year_level' => null,
        ])->with('archiveMode', true);
    }

    private function printWithFilters(array $filters): View
    {
        $event = filled($filters['event_schedule_id'])
            ? EventSchedule::find($filters['event_schedule_id'])
            : null;
        $records = $this->filteredRecords($filters)->get();
        $studentFiltersApplied = $this->studentFiltersApplied($filters);
        $summary = $this->eventSummaries(
            $records,
            $this->studentCount($filters),
            $studentFiltersApplied
        )->first();
        $sections = $this->printSections($records, $filters);

        return view('records.attendance-print', [
            'event' => $event,
            'summary' => $summary,
            'sections' => $sections,
            'departmentSummaries' => $this->departmentSummaries($sections),
            'filters' => $filters,
            'groupLabel' => $this->groupLabel($filters),
            'groupedPrint' => ! $this->studentGroupFiltersComplete($filters),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AttendanceRecord::create($this->validated($request));

        return redirect()
            ->route('records')
            ->with('attendance_status', 'Attendance record saved.');
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $attendanceRecord->update($this->validated($request));

        return redirect()
            ->route('records')
            ->with('attendance_status', 'Attendance record updated.');
    }

    public function destroy(AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $attendanceRecord->delete();

        return redirect()
            ->route('records')
            ->with('attendance_status', 'Attendance record deleted.');
    }

    private function filteredRecords(array $filters)
    {
        return AttendanceRecord::query()
            ->with(['eventSchedule', 'booth', 'student'])
            ->whereNotNull('event_schedule_id')
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('scanned_at', $date))
            ->when(
                $filters['event_schedule_id'] ?? null,
                fn ($query, $eventId) => $query->where('event_schedule_id', $eventId),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->when($this->studentFiltersApplied($filters), function ($query) use ($filters) {
                $query->whereHas('student', fn ($studentQuery) => $this->applyStudentFilters($studentQuery, $filters));
            })
            ->latest('scanned_at');
    }

    private function defaultEvent(array $filters): ?EventSchedule
    {
        if (! blank($filters['date'])) {
            return EventSchedule::query()
                ->whereDate('event_date', $filters['date'])
                ->orderByDesc('start_time')
                ->first();
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        $currentEvent = EventSchedule::query()
            ->whereDate('event_date', $now->toDateString())
            ->where('status', 'In Progress')
            ->orderByDesc('start_time')
            ->first();

        if ($currentEvent) {
            return $currentEvent;
        }

        $currentEvent = EventSchedule::query()
            ->whereDate('event_date', $now->toDateString())
            ->where('status', '!=', 'Cancelled')
            ->whereTime('start_time', '<=', $now->format('H:i:s'))
            ->whereTime('end_time', '>=', $now->format('H:i:s'))
            ->orderByDesc('start_time')
            ->first();

        if ($currentEvent) {
            return $currentEvent;
        }

        return EventSchedule::query()
            ->orderByDesc('event_date')
            ->orderByDesc('start_time')
            ->first();
    }

    private function filterOptions(): array
    {
        return [
            'events' => EventSchedule::query()->orderByDesc('event_date')->orderBy('title')->get(),
            'booths' => Booth::query()->orderBy('name')->get(),
            'students' => Student::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'courses' => Student::query()->select('course')->whereNotNull('course')->where('course', '!=', '')->distinct()->orderBy('course')->pluck('course'),
            'blocks' => Student::query()->select('block')->whereNotNull('block')->where('block', '!=', '')->distinct()->orderBy('block')->pluck('block'),
            'year_levels' => Student::query()->select('year_level')->whereNotNull('year_level')->where('year_level', '!=', '')->distinct()->orderBy('year_level')->pluck('year_level'),
        ];
    }

    private function studentCount(array $filters): int
    {
        return $this->applyStudentFilters(Student::query(), $filters)->count();
    }

    private function studentFiltersApplied(array $filters): bool
    {
        return filled($filters['course'] ?? null)
            || filled($filters['block'] ?? null)
            || filled($filters['year_level'] ?? null);
    }

    private function studentGroupFiltersComplete(array $filters): bool
    {
        return filled($filters['course'] ?? null)
            && filled($filters['block'] ?? null)
            && filled($filters['year_level'] ?? null);
    }

    private function groupLabel(array $filters): string
    {
        if (! $this->studentGroupFiltersComplete($filters)) {
            if (! $this->studentFiltersApplied($filters)) {
                return 'All Attendance Groups';
            }

            return collect([
                $filters['course'] ?? null,
                $filters['year_level'] ?? null,
                filled($filters['block'] ?? null) ? 'Block '.$filters['block'] : null,
            ])->filter()->implode(' ');
        }

        return "{$filters['course']} {$filters['year_level']} Block {$filters['block']}";
    }

    private function printSections($records, array $filters)
    {
        return $records
            ->filter(fn (AttendanceRecord $record) => $record->student)
            ->groupBy(function (AttendanceRecord $record): string {
                $student = $record->student;

                return implode('|', [
                    $student->course ?: 'Unassigned Course',
                    $student->year_level ?: 'Unassigned Year Level',
                    $student->block ?: 'Unassigned Block',
                ]);
            })
            ->map(function ($groupRecords): array {
                $student = $groupRecords->first()->student;
                $groupFilters = [
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'block' => $student->block,
                ];
                $attendedCount = $groupRecords
                    ->pluck('student_id')
                    ->filter()
                    ->unique()
                    ->count();
                $totalStudents = $this->studentCount($groupFilters);
                $percentage = $totalStudents > 0
                    ? round(($attendedCount / $totalStudents) * 100, 1)
                    : 0;

                return [
                    'label' => $this->groupLabel($groupFilters),
                    'course' => $student->course ?: '-',
                    'year_level' => $student->year_level ?: '-',
                    'block' => $student->block ?: '-',
                    'attendees' => $this->attendeeRows($groupRecords),
                    'attended_count' => $attendedCount,
                    'total_students' => $totalStudents,
                    'percentage_label' => number_format($percentage, $percentage === floor($percentage) ? 0 : 1).'%',
                    'scan_count' => $groupRecords->count(),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    private function departmentSummaries($sections)
    {
        return $sections
            ->groupBy('course')
            ->map(function ($courseSections, string $course): array {
                $totalStudents = $courseSections->sum('total_students');
                $attendedCount = $courseSections->sum('attended_count');
                $average = $totalStudents > 0
                    ? round(($attendedCount / $totalStudents) * 100, 1)
                    : 0;

                return [
                    'course' => $course,
                    'attended_count' => $attendedCount,
                    'total_students' => $totalStudents,
                    'average_label' => number_format($average, $average === floor($average) ? 0 : 1).'%',
                    'scan_count' => $courseSections->sum('scan_count'),
                    'group_count' => $courseSections->count(),
                ];
            })
            ->sortBy('course')
            ->values();
    }

    private function applyStudentFilters($query, array $filters)
    {
        return $query
            ->when($filters['course'] ?? null, fn ($studentQuery, $course) => $studentQuery->where('course', $course))
            ->when($filters['block'] ?? null, fn ($studentQuery, $block) => $studentQuery->where('block', $block))
            ->when($filters['year_level'] ?? null, fn ($studentQuery, $yearLevel) => $studentQuery->where('year_level', $yearLevel));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'event_schedule_id' => ['required', 'exists:event_schedules,id'],
            'booth_id' => ['required', 'exists:booths,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'scanned_at' => ['required', 'date'],
            'scan_type' => ['sometimes', 'in:time_in,time_out,verification'],
        ]);
    }

    private function eventSummaries($records, int $totalStudents, bool $attendeesLoaded)
    {
        return $records
            ->groupBy(fn (AttendanceRecord $record) => $record->event_schedule_id)
            ->map(function ($eventRecords) use ($attendeesLoaded): array {
                $event = $eventRecords->first()->eventSchedule;
                $attendedCount = $eventRecords
                    ->pluck('student_id')
                    ->filter()
                    ->unique()
                    ->count();
                $attendees = $attendeesLoaded ? $this->attendeeRows($eventRecords) : collect();
                $booths = $eventRecords
                    ->map(fn (AttendanceRecord $record) => $record->booth?->name ?? 'Deleted booth')
                    ->unique()
                    ->values()
                    ->implode(', ');
                $latestScan = $eventRecords->sortByDesc('scanned_at')->first();

                return [
                    'event' => $event->title,
                    'date' => $event->event_date?->format('F j, Y') ?? $latestScan->scanned_at->format('F j, Y'),
                    'time' => $event->start_time.' - '.$event->end_time,
                    'booth' => $booths,
                    'attendees' => $attendees,
                    'attendees_loaded' => $attendeesLoaded,
                    'attended_count' => $attendedCount,
                    'scan_count' => $eventRecords->count(),
                ];
            })
            ->map(function (array $summary) use ($totalStudents): array {
                $percentage = $totalStudents > 0
                    ? round(($summary['attended_count'] / $totalStudents) * 100, 1)
                    : 0;

                return array_merge($summary, [
                    'total_students' => $totalStudents,
                    'percentage' => $percentage,
                    'percentage_label' => number_format($percentage, $percentage === floor($percentage) ? 0 : 1).'%',
                ]);
            })
            ->sortByDesc('date')
            ->values();
    }

    private function attendeeRows($records)
    {
        return $records
            ->filter(fn (AttendanceRecord $record) => $record->student)
            ->groupBy('student_id')
            ->map(function ($studentRecords): array {
                $timeIn = $studentRecords
                    ->where('scan_type', AttendanceRecord::TYPE_TIME_IN)
                    ->sortBy('scanned_at')
                    ->first();
                $timeOut = $studentRecords
                    ->whereIn('scan_type', [AttendanceRecord::TYPE_TIME_OUT, AttendanceRecord::TYPE_VERIFICATION])
                    ->sortByDesc('scanned_at')
                    ->first();
                $firstRecord = $timeIn ?? $studentRecords->sortBy('scanned_at')->first();
                $student = $firstRecord->student;
                $attendanceStatus = null;

                if ($timeIn && $timeIn->eventSchedule) {
                    $event = $timeIn->eventSchedule;
                    $eventStartsAt = CarbonImmutable::parse(
                        $event->event_date->format('Y-m-d').' '.$event->start_time,
                        config('app.timezone')
                    );
                    $attendanceStatus = $timeIn->scanned_at->gt($eventStartsAt->addMinutes(15))
                        ? 'Late'
                        : 'On Time';
                }

                return [
                    'student_id' => $student->student_id,
                    'name' => trim($student->first_name.' '.$student->last_name),
                    'last_name' => $student->last_name,
                    'first_name' => $student->first_name,
                    'time_in' => $timeIn?->scanned_at?->format('g:i A'),
                    'time_out' => $timeOut?->scanned_at?->format('g:i A'),
                    'time_in_status' => $timeIn ? 'Done' : 'Pending',
                    'time_out_status' => $timeOut ? 'Done' : 'Pending',
                    'attendance_status' => $attendanceStatus ?? 'Pending',
                ];
            })
            ->sortBy([
                ['last_name', 'asc'],
                ['first_name', 'asc'],
            ])
            ->values();
    }

    private function formatRecord(AttendanceRecord $record): array
    {
        $student = $record->student;
        $studentName = $student
            ? trim($student->first_name.' '.$student->last_name)
            : 'Unassigned student';

        return [
            'id' => $record->id,
            'event' => $record->eventSchedule?->title ?? 'No event assigned',
            'booth' => $record->booth?->name ?? 'Deleted booth',
            'student' => $studentName,
            'student_id' => $student?->student_id ?? 'N/A',
            'date' => $record->scanned_at->format('F j, Y'),
            'time' => $record->scanned_at->format('g:i A'),
            'scanned_at' => $record->scanned_at->format('Y-m-d\TH:i'),
        ];
    }
}
