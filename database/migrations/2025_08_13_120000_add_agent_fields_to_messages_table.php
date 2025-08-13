<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('role')->nullable()->after('chat_id'); // user, assistant, tool, system, thinking
            $table->string('name')->nullable()->after('role'); // tool name or assistant name
            $table->string('call_id')->nullable()->after('name'); // tool call id
            $table->json('content_json')->nullable()->after('content'); // structured payload for tool args/results
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['role', 'name', 'call_id', 'content_json']);
        });
    }
};
