<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DSP-06: the workload colour limits become dispatch settings. The defaults are the blueprint's 60 and 90 percent. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('dispatch_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('workload_green_below')->default(60)->after('job_minutes');
            $table->unsignedSmallInteger('workload_amber_up_to')->default(90)->after('workload_green_below');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_settings', function (Blueprint $table) {
            $table->dropColumn(['workload_green_below', 'workload_amber_up_to']);
        });
    }
};
