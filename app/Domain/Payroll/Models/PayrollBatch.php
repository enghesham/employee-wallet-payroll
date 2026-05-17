<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Payroll\Enums\PayrollBatchStatus;
use Database\Factories\PayrollBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBatch extends Model
{
    /** @use HasFactory<PayrollBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'provider',
        'provider_batch_id',
        'status',
        'currency',
        'total_amount',
        'total_events',
        'processed_events',
        'failed_events',
        'metadata',
        'started_at',
        'completed_at',
        'failure_reason',
    ];

    protected static function newFactory(): PayrollBatchFactory
    {
        return PayrollBatchFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => PayrollBatchStatus::class,
            'total_amount' => 'decimal:4',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(PayrollEvent::class);
    }
}
