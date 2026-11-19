<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailySummaryConfig;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\DailySummary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The daily summary settings of each role (Reports-Summaries-Preferences spec SUM-01, D10). Route gate level:notification.manage. */
class SummarySettingsController extends Controller
{
    public function index(DailySummary $summary)
    {
        $tid = auth()->user()->tenant_id;

        return view('admin.summary-settings.index', [
            'roles' => collect(DailySummary::ROLES)->mapWithKeys(fn ($r) => [$r => $summary->config($tid, $r) + ['blocks_all' => DailySummary::BLOCKS[$r]]])->all(),
            'canEdit' => \App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'notification.manage'),
        ]);
    }

    public function update(Request $request, string $baseRole)
    {
        $role = $baseRole;
        abort_unless(in_array($role, DailySummary::ROLES, true), 404);
        $data = $request->validate([
            'send_time' => ['required', 'date_format:H:i'],
            'days'      => ['required', 'array', 'min:1'], 'days.*' => ['integer', 'between:1,7'],
            'blocks'    => ['nullable', 'array'], 'blocks.*' => [Rule::in(array_keys(DailySummary::BLOCKS[$role]))],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $tid = auth()->user()->tenant_id;

        $old = DailySummaryConfig::withoutGlobalScopes()->where('tenant_id', $tid)->where('base_role', $role)->first()?->only(['send_time', 'days_of_week_json', 'content_blocks_json', 'is_active']);
        $row = DailySummaryConfig::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $tid, 'base_role' => $role], [
            'send_time' => $data['send_time'] . ':00', 'days_of_week_json' => array_map('intval', $data['days']),
            'content_blocks_json' => array_values($data['blocks'] ?? []), 'is_active' => $request->boolean('is_active'),
        ]);
        app(AuditLogger::class)->log('summary_settings.changed', [
            'tenant_id' => $tid, 'item_type' => DailySummaryConfig::class, 'item_id' => $row->id, 'old' => $old,
            'new' => $row->only(['base_role', 'send_time', 'days_of_week_json', 'content_blocks_json', 'is_active']),
        ]);

        return back()->with('success', 'Saved.');
    }
}
