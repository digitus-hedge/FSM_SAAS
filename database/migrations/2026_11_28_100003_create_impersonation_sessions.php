<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support impersonation (blueprint 7.9a, 11.3). One row for each session; the audit rows written during it carry impersonation_session_id.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_user_id')->constrained('platform_users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('target_user_id');
            $table->string('reason', 255);
            // dateTime, not timestamp: avoids MariaDB's implicit defaults (1067 zero-date error,
            // and ON UPDATE CURRENT_TIMESTAMP silently rewriting started_at)
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('end_reason', 20)->nullable();                 // ended, expired, signed_out
            $table->unsignedInteger('actions_count')->default(0);
            $table->timestamps();

            $table->index(['platform_user_id', 'ended_at']);
            $table->index(['tenant_id', 'started_at']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonation_session_id')->nullable();
            $table->unsignedBigInteger('impersonated_by_platform_user_id')->nullable();
            $table->index('impersonation_session_id', 'idx_impersonation');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_impersonation');
            $table->dropColumn(['impersonation_session_id', 'impersonated_by_platform_user_id']);
        });
        Schema::dropIfExists('impersonation_sessions');
    }
};