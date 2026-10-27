<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('interest')->nullable()->after('language');
            $table->json('details')->nullable()->after('interest');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('opted_out_at')->nullable()->after('handover_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('opted_out_at');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['interest', 'details', 'consented_at', 'opted_out_at']);
        });
    }
};
