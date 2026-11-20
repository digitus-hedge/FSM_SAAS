<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The `sla:check` job reads open requests by company and status. idx_tenant_sla already covers the resolution due time; this covers the response one. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'sla_response_due_at'], 'idx_tenant_sla_response');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex('idx_tenant_sla_response');
        });
    }
};
