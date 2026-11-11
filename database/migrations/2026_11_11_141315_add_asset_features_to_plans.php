<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the asset plan features to each plan's existing features JSON (other keys are left alone).
 * assets_basic: all plans. assets_full (QR, Asset 360, import, scan): Professional and above.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::all() as $plan) {
            $features = $plan->features ?? [];
            $features['assets_basic'] = true;
            $features['assets_full']  = $plan->code !== 'starter';
            $plan->features = $features;
            $plan->save();
        }
    }

    public function down(): void
    {
        foreach (Plan::all() as $plan) {
            $features = $plan->features ?? [];
            unset($features['assets_basic'], $features['assets_full']);
            $plan->features = $features;
            $plan->save();
        }
    }
};