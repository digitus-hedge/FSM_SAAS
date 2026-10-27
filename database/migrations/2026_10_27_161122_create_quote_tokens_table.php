<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('quote_tokens', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('quote_id');
            $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->string('recipient_name', 150);
            $t->char('token', 64)->unique();
            $t->enum('sent_via', ['whatsapp','email'])->default('whatsapp');
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('viewed_first_at')->nullable();
            $t->unsignedInteger('viewed_count')->default(0);
            $t->timestamps();
            $t->index(['quote_id', 'client_contact_id']);
            $t->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('quote_tokens'); }
};