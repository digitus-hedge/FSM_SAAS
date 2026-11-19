<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reports-Summaries-Preferences spec: SCH-B8-01 to SCH-B8-06. No WhatsApp value exists in any column. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summary_configs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('base_role', 40);
            $t->time('send_time')->default('07:00:00');
            $t->json('days_of_week_json');
            $t->json('content_blocks_json');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'base_role'], 'uq_summary_role');
        });

        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('event_key', 180);
            $t->enum('channel', ['email', 'in_app', 'push']);
            $t->enum('frequency', ['instant', 'hourly_digest', 'daily_digest', 'off'])->default('instant');
            $t->timestamps();
            $t->unique(['user_id', 'event_key', 'channel'], 'uq_pref');
        });

        Schema::create('notification_digest_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('event_key', 180);
            $t->enum('channel', ['email', 'push']);
            $t->enum('frequency', ['hourly_digest', 'daily_digest']);
            $t->string('title', 255);
            $t->text('body')->nullable();
            $t->string('link', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('sent_at')->nullable();
            $t->index(['user_id', 'channel', 'frequency', 'sent_at'], 'idx_digest_due');
        });

        Schema::create('daily_summary_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->date('run_date');
            $t->timestamp('sent_at')->nullable();
            $t->unsignedSmallInteger('blocks_count')->default(0);
            $t->unique(['user_id', 'run_date'], 'uq_summary_user_day');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->boolean('daily_summary_on')->default(true);
            $t->time('digest_daily_time')->default('08:00:00');
        });

        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            $f['reports_full'] = $plan->code !== 'starter';
            $f['brand_pdf']    = $plan->code !== 'starter';
            $plan->features = $f;
            $plan->save();
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['daily_summary_on', 'digest_daily_time']));
        Schema::dropIfExists('daily_summary_runs');
        Schema::dropIfExists('notification_digest_items');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('daily_summary_configs');
        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            unset($f['reports_full'], $f['brand_pdf']);
            $plan->features = $f;
            $plan->save();
        }
    }
};
