<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sr_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_request_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('type', 40);          // assigned | accepted | punch_in | hold | resume | punch_out
            $t->string('label', 150)->nullable();  // 'Bad weather' etc.
            $t->text('note')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->timestamps();

            $t->index(['service_request_id', 'occurred_at']);
            $t->index(['tenant_id', 'service_request_id']);

            $t->foreign('service_request_id')
              ->references('id')->on('service_requests')
              ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sr_events');
    }
};