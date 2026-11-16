<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_time_off')) {
            return;
        }
        Schema::create('user_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('type', ['leave', 'training', 'other'])->default('leave');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'starts_at'], 'idx_tenant_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_time_off');
    }
};
