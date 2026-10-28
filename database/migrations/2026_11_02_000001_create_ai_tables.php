<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra answers the owner teaches the AI (on top of services, hours and the menu texts).
        Schema::create('knowledge_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->json('answer');                         // {en, zh}
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Every AI call, so the owner can see the cost and the cap can be enforced.
        Schema::create('ai_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 12);                  // reply, suggest, summary, preview
            $table->string('provider', 10);                 // claude or fake
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('status', 12);                   // ok, blocked, error
            $table->string('note', 160)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['business_id', 'created_at']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('ai_intent', 20)->nullable()->after('handover_reason');
            $table->string('ai_mood', 12)->nullable()->after('ai_intent');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $t) => $t->dropColumn(['ai_intent', 'ai_mood']));
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('knowledge_entries');
    }
};
