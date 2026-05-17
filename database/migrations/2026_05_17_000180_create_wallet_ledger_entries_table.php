<?php

use App\Domain\Wallets\Enums\WalletLedgerEntryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('type');
            $table->string('direction');
            $table->decimal('amount', 19, 4);
            $table->decimal('available_balance_before', 19, 4);
            $table->decimal('available_balance_after', 19, 4);
            $table->decimal('reserved_balance_before', 19, 4);
            $table->decimal('reserved_balance_after', 19, 4);
            $table->char('currency', 3);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status')->default(WalletLedgerEntryStatus::Posted->value)->index();
            $table->string('reason')->nullable();
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['employee_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['type', 'status']);
            $table->index('reference');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_available_before_non_negative CHECK (available_balance_before >= 0)');
            DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_available_after_non_negative CHECK (available_balance_after >= 0)');
            DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_reserved_before_non_negative CHECK (reserved_balance_before >= 0)');
            DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_reserved_after_non_negative CHECK (reserved_balance_after >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
    }
};
