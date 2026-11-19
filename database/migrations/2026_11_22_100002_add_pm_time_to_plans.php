<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/** Preventive-Maintenance spec D2 (blueprint section 6, "Time-based preventive maintenance"): Professional and Enterprise yes, Starter no. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            $f['pm_time'] = $plan->code !== 'starter';
            $plan->features = $f;
            $plan->save();
        }
    }

    public function down(): void
    {
        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            unset($f['pm_time']);
            $plan->features = $f;
            $plan->save();
        }
    }
};
