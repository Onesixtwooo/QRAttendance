<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const TYPE_TIME_IN = 'time_in';
    public const TYPE_TIME_OUT = 'time_out';
    public const TYPE_VERIFICATION = 'verification';

    protected $fillable = [
        'event_schedule_id',
        'booth_id',
        'student_id',
        'scanned_at',
        'scan_type',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    public function eventSchedule(): BelongsTo
    {
        return $this->belongsTo(EventSchedule::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
