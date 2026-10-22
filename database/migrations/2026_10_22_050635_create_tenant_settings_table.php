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
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('reopen_window_days')->default(14);  // §5 CLAUDE.md
            $table->unsignedSmallInteger('feedback_link_days')->default(7);
            $table->unsignedSmallInteger('client_sign_in_link_hours')->default(24);  // D7
            $table->boolean('require_two_factor')->default(false);  // D8
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_settings'); }
};
