<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendanceArchive extends Model
{
    protected $fillable = [
        'original_event_id',
        'event_title',
        'event_date',
        'report_html',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }
}
