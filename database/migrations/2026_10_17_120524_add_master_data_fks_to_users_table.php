<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add the columns first (if they don't exist yet)
            if (!Schema::hasColumn('users', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('client_id');
            }
        });

        // Add FKs in a separate schema call so the columns are definitely there
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();

            $table->index('client_id');
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropColumn(['client_id', 'vendor_id']);
        });
    }
};