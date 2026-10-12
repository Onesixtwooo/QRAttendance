<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSchedule extends Model
{
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
        ];
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
