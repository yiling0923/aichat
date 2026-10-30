<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->text('keywords')->nullable()->after('answer');
        });

        // What customers typed that the bot could not answer, so the owner can teach it.
        Schema::create('unanswered_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('text', 200);
            $table->string('normalized', 200);
            $table->string('language', 5)->nullable();
            $table->string('channel', 20)->nullable();
            $table->unsignedInteger('times')->default(1);
            $table->timestamp('last_asked_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unanswered_questions');
        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->dropColumn('keywords');
        });
    }
};
