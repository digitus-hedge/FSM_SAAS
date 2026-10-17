<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete(); // null = tenant workshop
            $table->string('site_code', 40);
            $table->string('name', 180);
            $table->enum('site_type', ['client_site','tenant_workshop'])->default('client_site');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius_meters')->nullable(); // overrides category radius
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('access_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id','site_code'], 'uq_tenant_site_code');
            $table->index(['tenant_id','client_id']);
            $table->index(['tenant_id','is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('project_sites'); }
};