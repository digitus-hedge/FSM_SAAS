<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The message log (blueprint 11.10, `message_logs`): one row for each message the system tries to send to a person outside the app.
 * `channel` is a plain string ('email' now; 'whatsapp' and 'push' later) and `status` too, so a new channel or status needs no migration.
 * The row never holds a secure-link token, a sign-in or signature code, a password or the message text: `payload_json` keeps only the ids
 * needed to build the message again for a retry.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('message_logs')) {
            return;
        }

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('direction', 10)->default('outbound');
            $table->string('channel', 20)->default('email');
            $table->string('event_key', 60)->nullable();
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->unsignedBigInteger('recipient_contact_id')->nullable();
            $table->string('recipient_address', 190);
            $table->string('status', 24)->default('queued');            // queued, sent, failed (the blueprint's other statuses come with WhatsApp)
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('mailable', 160)->nullable();                  // the class that builds the message, used by the retry
            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('payload_json')->nullable();                     // ids only
            $table->timestamp('first_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'idx_ml_tenant_status');
            $table->index(['tenant_id', 'created_at'], 'idx_ml_tenant_time');
            $table->index(['tenant_id', 'related_type', 'related_id'], 'idx_ml_related');
            $table->index(['tenant_id', 'channel', 'direction', 'created_at'], 'idx_ml_usage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
