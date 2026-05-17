<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Enums\IdempotencyRecordStatus;
use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    protected $fillable = [
        'scope',
        'key',
        'request_hash',
        'status',
        'response_code',
        'response_body',
        'locked_until',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyRecordStatus::class,
            'response_body' => 'array',
            'locked_until' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
