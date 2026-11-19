<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Forms-Approvals-Batch spec D2 and D9 (blueprint section 6, "Dynamic form builder" and "Approval modes").
 * forms: Professional and Enterprise. form_templates_limit: 10 for Professional, none for Enterprise.
 * approval_auto: the two automatic modes, Professional and Enterprise. approval_finance: Finance and manager, Enterprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            $f['forms']                 = $plan->code !== 'starter';
            $f['approval_auto']         = $plan->code !== 'starter';
            $f['approval_finance']      = $plan->code === 'enterprise';
            $f['form_templates_limit']  = $plan->code === 'professional' ? 10 : null;
            $plan->features = $f;
            $plan->save();
        }
    }

    public function down(): void
    {
        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            unset($f['forms'], $f['approval_auto'], $f['approval_finance'], $f['form_templates_limit']);
            $plan->features = $f;
            $plan->save();
        }
    }
};
