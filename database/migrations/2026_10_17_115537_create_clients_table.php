<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('client_code', 40);
            $table->string('name', 180);
            $table->string('contact_person', 150)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_id', 60)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->boolean('asset_visibility_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id','client_code'], 'uq_tenant_client_code');
            $table->index(['tenant_id','is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('clients'); }
};