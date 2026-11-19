<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SCH-B7-03. The reminder schedule (D14). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $t) {
            $t->boolean('overdue_reminders_enabled')->default(true);
            $t->unsignedSmallInteger('overdue_first_after_days')->default(1);
            $t->unsignedSmallInteger('overdue_repeat_days')->default(7);
            $t->unsignedSmallInteger('overdue_max_reminders')->default(4);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $t) {
            $t->dropColumn(['overdue_reminders_enabled', 'overdue_first_after_days', 'overdue_repeat_days', 'overdue_max_reminders']);
        });
    }
};
