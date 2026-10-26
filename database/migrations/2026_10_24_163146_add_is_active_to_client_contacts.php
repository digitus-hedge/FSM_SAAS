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
    Schema::table('client_contacts', function (Blueprint $table) {
        $table->boolean('is_active')->default(true)->after('is_primary');
    });
}

public function down(): void
{
    Schema::table('client_contacts', fn (Blueprint $t) => $t->dropColumn('is_active'));
}
};
