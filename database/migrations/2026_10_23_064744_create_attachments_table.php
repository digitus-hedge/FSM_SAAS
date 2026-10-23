<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // Tenant scoping
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Polymorphic owner — any model can have attachments
            $table->string('attachable_type', 60);           // 'App\Models\ServiceRequest' | 'App\Models\Client' | ...
            $table->unsignedBigInteger('attachable_id');

            // Kind of attachment
            $table->enum('kind', [
                'sr_attachment',    // SR general attachment
                'before_photo',     // before-work photo on a punch session
                'after_photo',      // after-work photo
                'signature',        // customer signature image
                'receipt',          // expense receipt
                'document',         // generic document
                'logo',             // tenant/client logo
                'asset_photo',      // asset nameplate photo
                'report',           // generated field report
                'quote_pdf',        // generated quote PDF
                'invoice_pdf',      // generated invoice PDF
            ])->default('document');

            // File metadata
            $table->string('storage_path', 500);              // path within the public disk
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->nullable();           // content hash for dedup / integrity

            // Capture metadata (for photos taken on-site)
            $table->timestamp('captured_at')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();

            // Uploader
            $table->foreignId('uploaded_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Idempotency key — client sends the same UUID on retry, we don't double-insert
            $table->char('client_event_uuid', 36)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unique on (tenant, event uuid) — enforces idempotency per tenant
            $table->unique(['tenant_id', 'client_event_uuid'], 'uq_tenant_event');

            // Primary lookup — "all attachments for this SR, of this kind"
            $table->index(
                ['tenant_id', 'attachable_type', 'attachable_id', 'kind'],
                'idx_tenant_owner'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};