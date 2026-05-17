<?php

use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('type')->default(WalletType::Salary->value);
            $table->char('currency', 3);
            $table->decimal('available_balance', 19, 4)->default('0.0000');
            $table->decimal('reserved_balance', 19, 4)->default('0.0000');
            $table->string('status')->default(WalletStatus::Active->value)->index();
            $table->timestampsTz();

            $table->unique(['employee_id', 'type', 'currency']);
            $table->index(['employee_id', 'status']);
            $table->index(['currency', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_available_balance_non_negative CHECK (available_balance >= 0)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_reserved_balance_non_negative CHECK (reserved_balance >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
