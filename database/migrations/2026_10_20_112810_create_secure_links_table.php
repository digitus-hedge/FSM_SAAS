<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secure_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // SCH-11: user_invite, password_reset, client_sign_in, email_change
            $table->enum('purpose', ['user_invite', 'password_reset', 'client_sign_in', 'email_change']);
            $table->char('token_hash', 64)->unique();  // SHA-256 of the random token
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->ipAddress('request_ip')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'purpose']);
            $table->index(['purpose', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_links');
    }
};
