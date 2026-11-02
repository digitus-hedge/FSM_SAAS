<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('login_history', function (Blueprint $table) {
            // 'user' for App\Models\User, 'platform_user' for App\Models\PlatformUser — same convention as user_devices.
            $table->string('user_type', 30)->default('user')->after('id');
            $table->index(['user_type', 'user_id', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            $table->dropIndex(['login_history_user_type_user_id_logged_in_at_index']);
            $table->dropColumn('user_type');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};