<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The platform console's own colour and font, chosen from the theme dock (like a company's Branding). Nullable: no choice = mint and the default font. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->string('brand_color', 7)->nullable();
            $table->string('font_key', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->dropColumn(['brand_color', 'font_key']);
        });
    }
};
