<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SCH-B7-04. Sending once, stopping and pausing reminders (D17, D18). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->unsignedSmallInteger('reminder_count')->default(0);
            $t->timestamp('last_reminder_at')->nullable();
            $t->timestamp('reminders_paused_at')->nullable();
            $t->string('reminders_paused_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn(['reminder_count', 'last_reminder_at', 'reminders_paused_at', 'reminders_paused_reason']);
        });
    }
};
