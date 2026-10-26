<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            if (! Schema::hasColumn('service_requests', 'hold_started_at')) {
                $t->timestamp('hold_started_at')->nullable()->after('current_hold_reason_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            if (Schema::hasColumn('service_requests', 'hold_started_at')) {
                $t->dropColumn('hold_started_at');
            }
        });
    }
};