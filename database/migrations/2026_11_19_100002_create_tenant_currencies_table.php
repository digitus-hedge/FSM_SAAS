<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SCH-B7-02. Which extra currencies a company may use (D1). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_currencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->char('currency_code', 3);
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['tenant_id', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_currencies');
    }
};
