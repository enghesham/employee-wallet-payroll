<?php

use App\Domain\Banking\Enums\WithdrawalRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('status')->default(WithdrawalRequestStatus::PendingBankConfirmation->value)->index();
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['wallet_id', 'status']);
            $table->index(['employee_id', 'status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE withdrawal_requests ADD CONSTRAINT withdrawal_requests_amount_positive CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
