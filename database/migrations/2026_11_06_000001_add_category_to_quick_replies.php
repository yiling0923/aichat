<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_replies', function (Blueprint $table): void {
            $table->string('category', 40)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('quick_replies', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
