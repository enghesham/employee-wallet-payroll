<?php

use App\Domain\Shared\Enums\ExternalEventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_type');
            $table->string('external_id');
            $table->string('status')->default(ExternalEventStatus::Received->value)->index();
            $table->json('payload');
            $table->json('response_payload')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'external_id']);
            $table->index(['provider', 'event_type']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_events');
    }
};
