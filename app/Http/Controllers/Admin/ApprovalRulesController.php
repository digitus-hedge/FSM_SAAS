<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Approval\ApprovalModes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The approval rules page (Forms-Approvals-Batch spec FRM-08, D20). The route group carries level:workflow.manage:
 * the Company Admin changes, the Operations Manager looks.
 */
class ApprovalRulesController extends Controller
{
    public function index()
    {
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        $modes  = app(ApprovalModes::class);

        return view('admin.approval-rules.index', [
            'categories' => ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->orderBy('name')->get(),
            'rules'      => ApprovalRule::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('service_category_id'),
            'allModes'   => ServiceCategory::APPROVAL_MODES,
            'allowed'    => collect(ServiceCategory::APPROVAL_MODES)->mapWithKeys(fn ($label, $m) => [$m => $modes->allowed($tenant, $m)])->all(),
            'finance'    => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->filter(fn ($u) => $u->canDo('expense.approve'))->values(),
        ]);
    }

    public function update(Request $request, ServiceCategory $category)
    {
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        abort_unless((int) $category->tenant_id === (int) $tenant->id, 404);

        $data = $request->validate([
            'mode'                    => ['required', Rule::in(array_keys(ServiceCategory::APPROVAL_MODES))],
            'amount_limit_base'       => ['nullable', 'numeric', 'min:0'],
            'second_approver_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenant->id)],
        ]);

        app(ApprovalModes::class)->saveRule($category, $tenant, $data['mode'], $data['amount_limit_base'] ?? null, $data['second_approver_user_id'] ?? null);

        return back()->with('success', "Approval rule for {$category->name} saved.");
    }
}
