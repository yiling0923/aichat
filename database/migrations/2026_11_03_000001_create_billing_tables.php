<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('plan', 30)->nullable();                 // null = internal/sample business with no limits
            $table->string('subscription_status', 20)->nullable();  // trialing, active, past_due, canceled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->string('paused_reason')->nullable();
            $table->timestamp('setup_done_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 30);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('provider', 20);
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('paid');
            $table->timestamps();
        });

        // Everyone who existed before sign-up with email checks counts as verified.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['plan', 'subscription_status', 'trial_ends_at', 'current_period_ends_at', 'paused_reason', 'setup_done_at']);
        });
    }
};
