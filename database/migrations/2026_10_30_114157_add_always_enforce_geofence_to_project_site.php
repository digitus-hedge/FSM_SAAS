<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('project_sites', function (Blueprint $t) {
            if (! Schema::hasColumn('project_sites', 'always_enforce_geofence')) {
                $t->boolean('always_enforce_geofence')->default(false)->after('geofence_radius_meters');
            }
        });
    }
    public function down(): void {
        Schema::table('project_sites', function (Blueprint $t) {
            if (Schema::hasColumn('project_sites', 'always_enforce_geofence')) {
                $t->dropColumn('always_enforce_geofence');
            }
        });
    }
};