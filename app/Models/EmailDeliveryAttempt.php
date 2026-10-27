<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDeliveryAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'kind',
        'recipient',
        'status',
        'error',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
