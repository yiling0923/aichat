<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('web');
            $table->string('token', 64)->unique();
            $table->string('contact_name')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('mode', 10)->default('bot');
            $table->string('status', 10)->default('open');
            $table->string('handover_reason')->nullable();
            $table->timestamp('handover_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->json('state')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
