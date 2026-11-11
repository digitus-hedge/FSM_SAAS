<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->enum('entity', ['clients', 'sites', 'assets', 'users']);
            // Blueprint column; nullable until the attachment API is wired in.
            $t->unsignedBigInteger('file_attachment_id')->nullable();
            // Where the uploaded CSV is kept (private disk) and its original name.
            $t->string('file_path', 255)->nullable();
            $t->string('original_name', 255)->nullable();
            $t->enum('status', ['uploaded', 'validated', 'committed', 'failed'])->default('uploaded');
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('valid_rows')->default(0);
            $t->unsignedInteger('error_rows')->default(0);
            $t->unsignedInteger('skipped_rows')->default(0);
            $t->unsignedBigInteger('error_report_attachment_id')->nullable();
            $t->unsignedBigInteger('created_by_user_id');
            $t->timestamp('committed_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'entity', 'status'], 'idx_tenant_entity_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};