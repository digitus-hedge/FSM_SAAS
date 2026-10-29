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
    Schema::table('project_sites', function (Blueprint $table) {
        $table->string('site_code', 40)->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('project_sites', function (Blueprint $table) {
        $table->string('site_code', 40)->nullable(false)->change();
    });
}
};
