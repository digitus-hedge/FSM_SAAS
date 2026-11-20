<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback by secure link (blueprint 7.7b and 9.2). A feedback link belongs to a request and a client contact, not to a user, so secure_links
 * gets an entity, a contact and a nullable user. The request keeps who answered, when and how (client_feedback_at, _source, _by).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('secure_links', function (Blueprint $table) {
            if (! Schema::hasColumn('secure_links', 'client_contact_id')) {
                $table->unsignedBigInteger('client_contact_id')->nullable()->after('user_id');
                $table->string('entity_type', 60)->nullable()->after('purpose');
                $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
                $table->index(['entity_type', 'entity_id', 'purpose'], 'idx_secure_links_entity');
            }
        });
        DB::statement('ALTER TABLE secure_links MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('service_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('service_requests', 'client_feedback_at')) {
                $table->timestamp('client_feedback_at')->nullable();
                $table->string('client_feedback_source', 20)->nullable();      // link | portal
                $table->string('client_feedback_by', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['client_feedback_at', 'client_feedback_source', 'client_feedback_by']);
        });
        Schema::table('secure_links', function (Blueprint $table) {
            $table->dropIndex('idx_secure_links_entity');
            $table->dropColumn(['client_contact_id', 'entity_type', 'entity_id']);
        });
    }
};
