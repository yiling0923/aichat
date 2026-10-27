<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                 // whatsapp, messenger, instagram
            $table->string('name', 100);
            $table->string('external_id', 100);         // WhatsApp phone number id, Facebook page id, Instagram account id
            $table->string('waba_id', 100)->nullable(); // WhatsApp Business Account id (needed for templates)
            $table->text('access_token');               // stored encrypted
            $table->json('settings')->nullable();
            $table->boolean('active')->default(true);
            $table->text('last_error')->nullable();
            $table->dateTime('last_inbound_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'external_id']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('channel_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
            $table->string('external_id', 100)->nullable()->after('channel_id'); // the customer's id on that channel
            $table->dateTime('last_inbound_at')->nullable()->after('last_message_at');
            $table->unique(['channel_id', 'external_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->string('external_id', 150)->nullable()->after('conversation_id');
            $table->index(['business_id', 'external_id']);
        });

        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);                 // lower case letters, numbers, underscores
            $table->string('language', 10)->default('en');
            $table->string('category', 20)->default('UTILITY');
            $table->text('body');                       // may contain {{1}}, {{2}}
            $table->string('status', 20)->default('draft'); // draft, PENDING, APPROVED, REJECTED, ...
            $table->string('meta_id', 60)->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
        Schema::table('messages', function (Blueprint $t): void {
            $t->dropIndex(['business_id', 'external_id']);
            $t->dropColumn('external_id');
        });
        Schema::table('conversations', function (Blueprint $t): void {
            $t->dropUnique(['channel_id', 'external_id']);
            $t->dropConstrainedForeignId('channel_id');
            $t->dropColumn(['external_id', 'last_inbound_at']);
        });
        Schema::dropIfExists('channels');
    }
};
