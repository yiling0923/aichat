<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When someone on the team last opened the chat. A customer message after this time means "unread".
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('staff_read_at')->nullable()->after('last_message_at');
        });

        // Chats that exist now count as read, so only new customer messages light up.
        DB::table('conversations')->update(['staff_read_at' => DB::raw('COALESCE(last_message_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('staff_read_at');
        });
    }
};
