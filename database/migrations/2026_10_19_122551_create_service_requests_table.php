<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();

            // Tenant scoping
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Numbering — unique per tenant (generated from tenant_sequences)
            $table->string('sr_number', 50);

            // Master/child relationship (self-referencing)
            $table->unsignedBigInteger('parent_sr_id')->nullable();
            $table->boolean('is_master_sr')->default(false);

            // Core relationships
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_site_id')->constrained('project_sites')->restrictOnDelete();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();

            // Release B references — add as plain unsigned bigint, FK added by back-fill migration later
            $table->unsignedBigInteger('primary_asset_id')->nullable();        // → assets (Release B)
            $table->unsignedBigInteger('contract_id')->nullable();              // → contracts (Release B)
            $table->unsignedBigInteger('pm_schedule_id')->nullable();           // → pm_schedules (Release B)
            $table->unsignedBigInteger('vendor_id')->nullable();                // → vendors (v3.2)

            // Service mode
            $table->enum('service_mode', ['on_site', 'depot'])->default('on_site');

            // Creation source
            $table->enum('source', [
                'front_desk', 'client_portal', 'pm_auto', 'qr_scan', 'api',
            ])->default('front_desk');

            // Coverage decision
            $table->enum('coverage_type', [
                'in_contract', 'in_warranty', 'out_of_coverage', 'goodwill',
            ])->default('out_of_coverage');

            $table->foreignId('coverage_override_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('coverage_override_reason', 255)->nullable();

            // v3.3 — manager has to choose which contract when multiple match
            $table->boolean('contract_choice_pending')->default(false);

            // v3.8 — quote and follow-up tracking
            $table->timestamp('quote_requested_at')->nullable();
            $table->unsignedBigInteger('follow_up_of_sr_id')->nullable();      // self FK added below

            // v3.3 — frozen snapshots from the category at creation
            $table->boolean('invoice_mandatory')->default(false);
            $table->boolean('enforce_geofence')->default(false);
            $table->boolean('allow_signature_waiver')->default(false);

            // Priority
            $table->enum('priority', [
                'low', 'medium', 'high', 'critical', 'emergency',
            ])->default('medium');

            // Status (10 values per blueprint v3.3 §7.1)
            $table->enum('status', [
                'pending', 'quoted', 'approved', 'assigned', 'in_progress',
                'pending_review', 'awaiting_invoice', 'rework', 'completed', 'cancelled',
            ])->default('pending');

            // Release C — custom stage codes between core statuses
            $table->string('custom_stage_code', 40)->nullable();

            // Hold flag (not a separate status)
            $table->boolean('is_on_hold')->default(false);
            $table->foreignId('current_hold_reason_id')->nullable()
                  ->constrained('hold_reasons')->nullOnDelete();

            // Billing track (runs parallel to status)
            $table->enum('billing_status', [
                'not_applicable', 'pending_invoice', 'invoiced',
                'partially_paid', 'paid', 'written_off',
            ])->default('not_applicable');

            // Request description
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('client_po_reference', 100)->nullable();

            // People involved
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_tech_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Dispatch & scheduling
            $table->unsignedSmallInteger('daily_route_sequence')->nullable();
            $table->unsignedBigInteger('form_template_version_id')->nullable(); // Release B

            // SLA tracking
            $table->timestamp('sla_clock_started_at')->nullable();
            $table->timestamp('sla_paused_at')->nullable();
            $table->unsignedInteger('sla_paused_minutes')->default(0);
            $table->timestamp('sla_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->timestamp('sla_response_warned_at')->nullable();
            $table->timestamp('sla_resolution_warned_at')->nullable();
            $table->boolean('sla_response_breached')->default(false);
            $table->boolean('sla_resolution_breached')->default(false);

            // Execution timestamps
            $table->timestamp('scheduled_eta_at')->nullable();
            $table->timestamp('tech_accepted_at')->nullable();
            $table->timestamp('first_punch_in_at')->nullable();
            $table->timestamp('last_punch_out_at')->nullable();
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->unsignedInteger('total_hold_minutes')->default(0);

            // Rework / reopen counters
            $table->unsignedTinyInteger('rework_count')->default(0);
            $table->unsignedTinyInteger('reopened_count')->default(0);

            // Frozen rules + checklist data
            $table->json('proof_rules_snapshot');
            $table->json('sr_checklist_payload')->nullable();

            // Customer signature at completion
            $table->string('customer_signer_name', 150)->nullable();
            $table->string('customer_signer_phone', 40)->nullable();
            $table->unsignedBigInteger('customer_signature_attachment_id')->nullable(); // → attachments
            $table->unsignedBigInteger('signature_waiver_otp_id')->nullable();          // → secure_links / otps
            $table->decimal('signature_gps_lat', 10, 7)->nullable();
            $table->decimal('signature_gps_lng', 10, 7)->nullable();
            $table->timestamp('signed_at')->nullable();

            // QC
            $table->foreignId('qc_reviewed_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('qc_reviewed_at')->nullable();
            $table->text('qc_rejection_reason')->nullable();

            // Terminal reasons
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Client feedback
            $table->unsignedTinyInteger('client_star_rating')->nullable();
            $table->unsignedTinyInteger('client_nps_score')->nullable();
            $table->text('client_feedback_comment')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /* ------------------------------------------------------------------
             | Keys, indexes, self-FKs
             | ------------------------------------------------------------------ */

            // Unique SR number per tenant
            $table->unique(['tenant_id', 'sr_number'], 'uq_tenant_sr_number');

            // Blueprint-specified indexes
            $table->index(['tenant_id', 'status', 'priority'],
                          'idx_tenant_status_priority');
            $table->index(['tenant_id', 'assigned_tech_user_id', 'status', 'scheduled_eta_at'],
                          'idx_tenant_tech_schedule');
            $table->index(['tenant_id', 'client_id', 'project_site_id'],
                          'idx_tenant_client_site');
            $table->index(['tenant_id', 'parent_sr_id'],
                          'idx_tenant_parent_sr');
            $table->index(['tenant_id', 'status', 'sla_resolution_due_at'],
                          'idx_tenant_sla');
        });

        // Self-referencing FKs — added after table exists so MySQL doesn't fail on forward refs
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign('parent_sr_id')->references('id')->on('service_requests')->nullOnDelete();
            $table->foreign('follow_up_of_sr_id')->references('id')->on('service_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['parent_sr_id']);
            $table->dropForeign(['follow_up_of_sr_id']);
        });
        Schema::dropIfExists('service_requests');
    }
};