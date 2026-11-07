<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('service_requests', function (Blueprint $table) {
        $table->text('qc_rework_reason')->nullable();
        $table->unsignedInteger('qc_rework_count')->default(0);
    });
}

public function down(): void
{
    Schema::table('service_requests', function (Blueprint $table) {
        $table->dropColumn(['qc_rework_reason', 'qc_rework_count']);
    });
}
};
