<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booth extends Model
{
    protected $fillable = [
        'name',
        'status',
        'last_seen_at',
        'user_id',
        'admin_name',
        'admin_email',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function markOffline(): void
    {
        $this->forceFill(['last_seen_at' => null])->save();
    }
}
