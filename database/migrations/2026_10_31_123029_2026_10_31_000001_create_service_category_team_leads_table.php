<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_category_team_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('team_lead_user_id');
            $table->unsignedBigInteger('service_category_id');
            $table->timestamps();

            $table->unique(['team_lead_user_id', 'service_category_id'], 'uq_team_lead_category');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('team_lead_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('service_category_id')->references('id')->on('service_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_category_team_leads');
    }
};