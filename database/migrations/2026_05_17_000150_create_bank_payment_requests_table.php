<?php

use App\Domain\Banking\Enums\BankPaymentRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('withdrawal_request_id')->constrained('withdrawal_requests')->restrictOnDelete();
            $table->string('provider')->default('mock_bank');
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('status')->default(BankPaymentRequestStatus::Pending->value)->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['withdrawal_request_id', 'status']);
            $table->index(['provider', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_payment_requests');
    }
};
