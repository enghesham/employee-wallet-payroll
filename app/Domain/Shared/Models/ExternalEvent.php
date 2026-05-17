<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Enums\ExternalEventStatus;
use Illuminate\Database\Eloquent\Model;

class ExternalEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'external_id',
        'status',
        'payload',
        'response_payload',
        'occurred_at',
        'processed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExternalEventStatus::class,
            'payload' => 'array',
            'response_payload' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
