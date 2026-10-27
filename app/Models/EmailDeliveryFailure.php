<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDeliveryFailure extends Model
{
    protected $fillable = [
        'kind',
        'recipient',
        'related_type',
        'related_id',
        'error',
        'status',
        'attempts',
        'last_attempted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'last_attempted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
