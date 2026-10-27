<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('quote_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('quote_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('type', 40);  // draft_made | saved | sent | downloaded | revised | accepted | rejected | expired
            $t->string('label', 300)->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->timestamps();
            $t->index(['quote_id', 'occurred_at']);
            $t->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('quote_events'); }
};