<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Forms-Approvals-Batch spec: SCH-B3-04 and SCH-B3-05. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $t) {
            $t->unsignedBigInteger('form_template_id')->nullable();
        });

        Schema::table('service_requests', function (Blueprint $t) {
            $t->enum('approval_state', ['awaiting_both', 'awaiting_ops', 'awaiting_finance', 'done'])->nullable();
            $t->unsignedBigInteger('approved_by_ops_user_id')->nullable();
            $t->unsignedBigInteger('approved_by_finance_user_id')->nullable();
            $t->string('auto_approved_mode', 30)->nullable();
            $t->enum('checklist_result', ['ok', 'issues'])->nullable();
            $t->timestamp('checklist_saved_at')->nullable();
        });

        Schema::table('service_request_assets', function (Blueprint $t) {
            $t->enum('checklist_result', ['ok', 'issues'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_request_assets', fn (Blueprint $t) => $t->dropColumn('checklist_result'));
        Schema::table('service_requests', fn (Blueprint $t) => $t->dropColumn([
            'approval_state', 'approved_by_ops_user_id', 'approved_by_finance_user_id', 'auto_approved_mode', 'checklist_result', 'checklist_saved_at',
        ]));
        Schema::table('assets', fn (Blueprint $t) => $t->dropColumn('form_template_id'));
    }
};
