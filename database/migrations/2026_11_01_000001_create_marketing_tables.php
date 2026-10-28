<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('key', 30);                     // survey, review_request, rebooking, no_show, hot_lead
            $table->boolean('enabled')->default(false);    // everything starts switched off
            $table->unsignedSmallInteger('delay_hours')->default(2);
            $table->json('message');                       // {en, zh} with {name} {service} {review_url}
            $table->string('template_name', 80)->nullable(); // approved WhatsApp template for when the 24-hour window is closed
            $table->json('settings')->nullable();          // rebook_days, review_url, min_rating
            $table->timestamps();
            $table->unique(['business_id', 'key']);
        });

        Schema::create('automation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('automation_key', 30);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12);                  // sent, skipped, failed
            $table->string('reason', 120)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['business_id', 'automation_key', 'created_at']);
        });

        Schema::create('surveys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('review_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('message');                       // {en, zh}
            $table->string('template_name', 80)->nullable();
            $table->json('audience')->nullable();          // tag_id, score, language
            $table->string('status', 10)->default('draft'); // draft, sent
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('broadcast_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12);
            $table->string('reason', 120)->nullable();
            $table->unique(['broadcast_id', 'customer_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 60)->nullable();
            $table->string('items', 500)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 12)->default('pending'); // pending, paid, refunded, cancelled
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automations');
    }
};
