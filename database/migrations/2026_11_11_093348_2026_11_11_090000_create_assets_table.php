<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('project_site_id');
            $table->unsignedBigInteger('parent_asset_id')->nullable();
            $table->unsignedBigInteger('service_category_id');

            $table->string('asset_code', 80);
            $table->string('qr_hash', 64);               // random, not guessable
            $table->string('name', 180);
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('location_within_site', 255)->nullable();
            $table->date('installation_date')->nullable();
            $table->date('warranty_expiry_date')->nullable();

            // Contracts arrive in B2, so no foreign key yet.
            $table->unsignedBigInteger('default_contract_id')->nullable();

            $table->enum('status', ['active', 'under_repair', 'inactive', 'decommissioned'])
                  ->default('active');

            // Meter fields are stored now; the screens come in Release C.
            $table->boolean('tracks_meter')->default(false);
            $table->string('meter_unit', 30)->nullable();
            $table->decimal('current_meter_reading', 12, 2)->unsigned()->default(0);
            $table->timestamp('last_meter_recorded_at')->nullable();

            $table->unsignedBigInteger('photo_attachment_id')->nullable();
            $table->json('specifications_json')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'asset_code'], 'uq_tenant_asset_code');
            $table->unique(['tenant_id', 'qr_hash'], 'uq_tenant_qr_hash');
            $table->index(['tenant_id', 'project_site_id', 'service_category_id'], 'idx_tenant_site_category');
            $table->index(['tenant_id', 'serial_number'], 'idx_tenant_serial');

            $table->foreign('client_id')->references('id')->on('clients');
            $table->foreign('project_site_id')->references('id')->on('project_sites');
            $table->foreign('service_category_id')->references('id')->on('service_categories');
            $table->foreign('parent_asset_id')->references('id')->on('assets');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};