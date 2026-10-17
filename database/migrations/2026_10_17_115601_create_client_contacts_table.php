<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp_number', 40)->nullable();
            $table->timestamp('whatsapp_opt_in_at')->nullable();
            $table->timestamp('whatsapp_opt_out_at')->nullable();
            $table->json('receives_events_json')->nullable();
            $table->json('site_scope_json')->nullable();                   // null = all sites
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id','client_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('client_contacts'); }
};