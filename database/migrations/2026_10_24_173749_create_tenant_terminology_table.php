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
        Schema::create('tenant_terminology', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('term_key', 60);      // service_request, technician, …
            $table->string('singular', 80);
            $table->string('plural', 80);
            $table->timestamps();

            $table->unique(['tenant_id', 'term_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_terminology');
    }
};
