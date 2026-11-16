<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'home_base_lat')) {
                $table->decimal('home_base_lat', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('users', 'home_base_lng')) {
                $table->decimal('home_base_lng', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('users', 'daily_capacity_minutes')) {
                $table->unsignedSmallInteger('daily_capacity_minutes')->default(480);
            }
        });

        if (! Schema::hasTable('dispatch_settings')) {
            Schema::create('dispatch_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('w_skill')->default(35);
                $table->unsignedTinyInteger('w_availability')->default(25);
                $table->unsignedTinyInteger('w_workload')->default(20);
                $table->unsignedTinyInteger('w_proximity')->default(10);
                $table->unsignedTinyInteger('w_first_fix')->default(10);
                $table->unsignedSmallInteger('job_minutes')->default(120);
                $table->timestamps();
                $table->unique('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_settings');
        Schema::table('users', function (Blueprint $table) {
            foreach (['home_base_lat', 'home_base_lng', 'daily_capacity_minutes'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
