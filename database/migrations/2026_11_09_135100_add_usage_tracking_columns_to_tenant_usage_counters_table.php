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
        Schema::table('tenant_usage_counters', function (Blueprint $table) {
            $table->unsignedInteger('user_count')->default(0)->after('requests_count');
            $table->unsignedInteger('whatsapp_message_count')->default(0)->after('user_count');
            $table->unsignedInteger('email_message_count')->default(0)->after('whatsapp_message_count');
            $table->json('warnings_json')->nullable()->after('email_message_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_usage_counters', function (Blueprint $table) {
            $table->dropColumn(['user_count', 'whatsapp_message_count', 'email_message_count', 'warnings_json']);
        });
    }
};