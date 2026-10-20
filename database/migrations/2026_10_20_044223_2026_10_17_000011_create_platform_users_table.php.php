<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190)->unique();
            $table->string('password');
            // D19: owner | support | finance. Finance is read-only until Release B.
            $table->enum('platform_role', ['owner', 'support', 'finance']);
            // SCH-09
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            // SCH-10. 2FA is MANDATORY for platform admins (D8).
            $table->text('two_factor_secret')->nullable(); // encrypted via cast
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_sign_in_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('platform_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_users');
    }
};