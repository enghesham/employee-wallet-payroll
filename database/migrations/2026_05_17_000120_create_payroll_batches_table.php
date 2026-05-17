<?php

use App\Domain\Payroll\Enums\PayrollBatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('mock_payroll');
            $table->string('provider_batch_id')->nullable();
            $table->string('status')->default(PayrollBatchStatus::Pending->value)->index();
            $table->char('currency', 3)->nullable();
            $table->decimal('total_amount', 19, 4)->default('0.0000');
            $table->unsignedInteger('total_events')->default(0);
            $table->unsignedInteger('processed_events')->default(0);
            $table->unsignedInteger('failed_events')->default(0);
            $table->json('metadata')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_batch_id']);
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payroll_batches ADD CONSTRAINT payroll_batches_total_amount_non_negative CHECK (total_amount >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_batches');
    }
};
