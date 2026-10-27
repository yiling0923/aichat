<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 7)->default('#0f766e');
            $table->string('type', 10)->default('customer'); // customer or chat
            $table->timestamps();
            $table->unique(['business_id', 'type', 'name']);
        });

        Schema::create('customer_tag', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'tag_id']);
        });

        Schema::create('conversation_tag', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['conversation_id', 'tag_id']);
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->dateTime('due_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('done_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'done_at', 'due_at']);
        });

        Schema::create('quick_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->json('body');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });

        // Replaced by the tags tables (the old JSON column would hide the relation).
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('tags'));

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->json('tags')->nullable());
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('active'));
        Schema::table('conversations', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('assigned_to');
        });
        Schema::dropIfExists('quick_replies');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('conversation_tag');
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
    }
};
