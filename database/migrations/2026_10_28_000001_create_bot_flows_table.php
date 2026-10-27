<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            // The owner edits the draft and publishes it when ready. Customers get the published copy.
            $table->json('draft');
            $table->json('published');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bot_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // option, lead, handover
            $table->string('node', 60)->nullable();
            $table->string('option_id', 60)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_events');
        Schema::dropIfExists('bot_flows');
    }
};
