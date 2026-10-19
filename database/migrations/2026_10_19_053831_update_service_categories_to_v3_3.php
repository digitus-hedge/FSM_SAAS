<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            // ADD — v3.3: new flag — SR cannot complete without an invoice
            if (!Schema::hasColumn('service_categories', 'invoice_mandatory')) {
                $table->boolean('invoice_mandatory')
                      ->default(false)
                      ->after('require_checklist');
            }
        });

        // REMOVE — these moved from category to per-site (project_sites) in v3.3
        Schema::table('service_categories', function (Blueprint $table) {
            if (Schema::hasColumn('service_categories', 'allow_signature_waiver')) {
                $table->dropColumn('allow_signature_waiver');
            }
            if (Schema::hasColumn('service_categories', 'require_gps_geofence')) {
                $table->dropColumn('require_gps_geofence');
            }
            if (Schema::hasColumn('service_categories', 'geofence_radius_meters')) {
                $table->dropColumn('geofence_radius_meters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            // Reverse: drop v3.3 add
            if (Schema::hasColumn('service_categories', 'invoice_mandatory')) {
                $table->dropColumn('invoice_mandatory');
            }
        });

        // Reverse: restore v3.2 columns
        Schema::table('service_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('service_categories', 'allow_signature_waiver')) {
                $table->boolean('allow_signature_waiver')->default(false)->after('require_signature');
            }
            if (!Schema::hasColumn('service_categories', 'require_gps_geofence')) {
                $table->boolean('require_gps_geofence')->default(false)->after('require_checklist');
            }
            if (!Schema::hasColumn('service_categories', 'geofence_radius_meters')) {
                $table->unsignedInteger('geofence_radius_meters')->default(250)->after('require_gps_geofence');
            }
        });
    }
};