<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();          // URL subdomain, e.g. 'acme'
            $table->string('name');                         // 'Acme Industries LLC'
            $table->string('status', 20)->default('active'); // active | suspended | pending

            // Branding
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#FFC61A');
            $table->string('timezone', 50)->default('Asia/Calcutta');
            $table->string('locale', 10)->default('en');

            // Contact / billing
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('country', 2)->nullable();

            // Lifecycle
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();

            // Future: plan_id, trial_ends_at, etc. (Module 13)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};