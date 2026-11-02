<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the platform owner did to a business (pause, plan change, support access ...). Never holds passwords or tokens.
        Schema::create('platform_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('details', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audits');
    }
};
