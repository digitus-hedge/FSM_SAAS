<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->text('reopen_reason')->nullable()->after('reopened_count');
            $table->timestamp('reopened_at')->nullable()->after('reopen_reason');
            $table->foreignId('reopened_by_user_id')->nullable()->after('reopened_at')
                ->constrained('users')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by_user_id');
            $table->dropColumn(['reopen_reason', 'reopened_at']);
        });
    }
};