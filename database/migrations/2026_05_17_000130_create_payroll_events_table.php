<?php

use App\Domain\Payroll\Enums\PayrollEventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_batch_id')->nullable()->constrained('payroll_batches')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->string('provider')->default('mock_payroll');
            $table->string('provider_event_id');
            $table->string('event_type');
            $table->string('payroll_employee_id')->nullable()->index();
            $table->decimal('amount', 19, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('status')->default(PayrollEventStatus::Received->value)->index();
            $table->json('payload');
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_event_id']);
            $table->index(['status', 'created_at']);
            $table->index(['employee_id', 'status']);
            $table->index(['wallet_id', 'processed_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payroll_events ADD CONSTRAINT payroll_events_amount_positive CHECK (amount IS NULL OR amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_events');
    }
};
