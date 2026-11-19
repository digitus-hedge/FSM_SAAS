<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the `multi_currency` and `billing_full` plan features (blueprint section 6).
 * Starter: no. Professional and Enterprise: yes. Other feature keys are left alone.
 */
return new class extends Migration
{
    private const KEYS = ['multi_currency', 'billing_full'];

    public function up(): void
    {
        foreach (Plan::all() as $plan) {
            $features = $plan->features ?? [];
            foreach (self::KEYS as $key) {
                $features[$key] = $plan->code !== 'starter';
            }
            $plan->features = $features;
            $plan->save();
        }
    }

    public function down(): void
    {
        foreach (Plan::all() as $plan) {
            $features = $plan->features ?? [];
            foreach (self::KEYS as $key) {
                unset($features[$key]);
            }
            $plan->features = $features;
            $plan->save();
        }
    }
};
