<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSchedule extends Model
{
    public const ATTENDANCE_GRACE_MINUTES = 20;

    public const TIME_OUT_OPEN_MINUTES_BEFORE_END = 30;

    public const PHASE_TIME_IN = 'time_in';

    public const PHASE_TIME_OUT = 'time_out';

    public const PHASE_VERIFICATION = 'verification';

    protected $fillable = [
        'title',
        'event_date',
        'start_time',
        'end_time',
        'venue',
        'booth',
        'status',
        'override_schedule_time',
        'scan_phase',
        'time_in_open',
    ];

    public function scanPhaseLabel(): string
    {
        return match ($this->scan_phase) {
            self::PHASE_TIME_OUT => 'Time-out',
            self::PHASE_VERIFICATION => 'Time-out',
            default => 'Time-in',
        };
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'time_in_open' => 'boolean',
            'override_schedule_time' => 'boolean',
        ];
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function syncAutomaticScanControls(?CarbonImmutable $now = null): void
    {
        if ($this->status !== 'In Progress') {
            return;
        }

        $now ??= CarbonImmutable::now(config('app.timezone'));
        $eventDate = CarbonImmutable::parse($this->event_date, config('app.timezone'));
        $endsAt = CarbonImmutable::parse($eventDate->format('Y-m-d').' '.$this->end_time, config('app.timezone'));

        if ($now->greaterThanOrEqualTo($endsAt->subMinutes(self::TIME_OUT_OPEN_MINUTES_BEFORE_END))
            && ($this->scan_phase !== self::PHASE_TIME_OUT || $this->time_in_open)) {
            $this->forceFill([
                'scan_phase' => self::PHASE_TIME_OUT,
                'time_in_open' => false,
            ])->saveQuietly();
        }
    }
}
