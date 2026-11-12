<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the `contracts` plan feature (blueprint section 6: Contracts and AMCs).
 * Starter: no. Professional and Enterprise: yes. Other feature keys are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::all() as $plan) {
            $features = $plan->features ?? [];
            $features['contracts'] = $plan->code !== 'starter';
            $plan->features = $features;
            $plan->save();
        }
    }

    public function down(): void
    {
        foreach (Plan::all() as $plan) {
            $features = $plan->features ?? [];
            unset($features['contracts']);
            $plan->features = $features;
            $plan->save();
        }
    }
};
