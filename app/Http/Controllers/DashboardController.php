<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $todayRecords = $this->timeInRecordsFor(today());
        $yesterdayRecords = $this->timeInRecordsFor(today()->subDay());
        $totalStudents = Student::count();
        $presentToday = $todayRecords->pluck('student_id')->filter()->unique()->count();
        $lateToday = $this->lateCount($todayRecords);
        $lateYesterday = $this->lateCount($yesterdayRecords);
        $activeBooths = Booth::where('status', '!=', 'Review')
            ->where('last_seen_at', '>', now()->subMinutes(2))
            ->count();
        $reviewBooths = Booth::where('status', 'Review')->count();
        $attendanceRate = $totalStudents > 0
            ? round(($presentToday / $totalStudents) * 100, 1)
            : 0;

        $hourlyCounts = collect(range(6, 12))->mapWithKeys(fn (int $hour): array => [
            $hour => $todayRecords->filter(fn (AttendanceRecord $record): bool => $record->scanned_at->hour === $hour)->count(),
        ]);
        $peakHourlyCount = max(1, (int) $hourlyCounts->max());
        $attendanceFlow = $hourlyCounts->map(fn (int $count, int $hour): array => [
            'label' => now()->startOfDay()->addHours($hour)->format('g A'),
            'count' => $count,
            'width' => round(($count / $peakHourlyCount) * 100),
        ])->values();

        return view('welcome', [
            'page' => 'dashboard',
            'stats' => [
                ['label' => 'Total Students', 'value' => number_format($totalStudents), 'change' => '+'.Student::where('created_at', '>=', now()->startOfMonth())->count().' this month', 'tone' => 'bg-emerald-50 text-emerald-700'],
                ['label' => 'Present Today', 'value' => number_format($presentToday), 'change' => number_format($attendanceRate, 1).'% attendance', 'tone' => 'bg-sky-50 text-sky-700'],
                ['label' => 'Late Entries', 'value' => number_format($lateToday), 'change' => $this->comparisonLabel($lateToday, $lateYesterday), 'tone' => 'bg-amber-50 text-amber-700'],
                ['label' => 'Active Booths', 'value' => number_format($activeBooths), 'change' => $reviewBooths.' need review', 'tone' => 'bg-rose-50 text-rose-700'],
            ],
            'attendanceFlow' => $attendanceFlow,
            'activeBooths' => $activeBooths,
        ]);
    }

    private function timeInRecordsFor($date): Collection
    {
        return AttendanceRecord::query()
            ->with('eventSchedule:id,start_time')
            ->where('scan_type', AttendanceRecord::TYPE_TIME_IN)
            ->whereDate('scanned_at', $date)
            ->get();
    }

    private function lateCount(Collection $records): int
    {
        return $records->filter(function (AttendanceRecord $record): bool {
            $startTime = $record->eventSchedule?->start_time;

            return $startTime && $record->scanned_at->format('H:i:s') > $startTime;
        })->count();
    }

    private function comparisonLabel(int $today, int $yesterday): string
    {
        $difference = $today - $yesterday;

        if ($difference === 0) {
            return 'Same as yesterday';
        }

        return ($difference > 0 ? '+' : '').$difference.' vs yesterday';
    }
}
