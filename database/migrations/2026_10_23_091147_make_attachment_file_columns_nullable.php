<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('storage_path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
            $table->string('mime_type')->nullable()->change();
            $table->unsignedBigInteger('size_bytes')->nullable()->change();
            $table->string('sha256')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Do not restore NOT NULL until all NULL values are handled.
    }
};