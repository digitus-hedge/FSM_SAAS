<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_notifications')) {
            return;
        }

        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('user_id');
            $t->string('event_key', 80);
            $t->string('title', 190);
            $t->text('body')->nullable();
            $t->string('url', 500)->nullable();
            $t->json('data')->nullable();
            $t->timestamp('emailed_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'read_at'], 'idx_un_user_read');
            $t->index(['tenant_id', 'event_key'], 'idx_un_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
