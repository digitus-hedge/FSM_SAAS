<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamLeadCategoriesController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        $teamLeadRoleId = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'team_lead')
            ->value('id');

        $teamLeads = User::where('tenant_id', $tenantId)
            ->when($teamLeadRoleId, fn ($q) => $q->where('role_id', $teamLeadRoleId))
            ->whereNull('deactivated_at')
            ->with('coveredCategories:id,name')
            ->orderBy('name')
            ->get();

        $categories = ServiceCategory::where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.team-lead-categories.index', compact('teamLeads', 'categories'));
    }

        public function update(Request $request, User $teamLead)
    {
        abort_unless($teamLead->tenant_id === auth()->user()->tenant_id, 403);

        $data = $request->validate([
            'category_ids'   => ['array'],
            'category_ids.*' => ['integer', 'exists:service_categories,id'],
        ]);

        $syncData = collect($data['category_ids'] ?? [])
            ->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $teamLead->tenant_id]])
            ->all();

        $teamLead->coveredCategories()->sync($syncData);

        return back()->with('success', "{$teamLead->name}'s categories updated.");
    }
}