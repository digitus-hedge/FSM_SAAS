<?php

namespace App\Http\Controllers\Admin;

use App\Services\Permissions\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

/** Overdue reminder settings (LED-04, spec 5.7 and D14). Plan: billing_full. Needs settings.manage or invoice.manage. */
class ReminderSettingsController extends Controller
{
    private const RANGES = [
        'overdue_first_after_days' => [0, 30],
        'overdue_repeat_days'      => [1, 30],
        'overdue_max_reminders'    => [1, 10],
    ];

    private function tenant()
    {
        $user = auth()->user();
        abort_unless($user && $user->tenant_id, 404);
        abort_unless(\App\Services\Permissions\PermissionLevel::canView($user, 'settings.manage', 'invoice.manage'), 404);
        abort_unless(request()->isMethodSafe() || PermissionLevel::canChange($user, 'settings.manage', 'invoice.manage'), 403);

        return $user->tenant;
    }

    public function edit()
    {
        $tenant = $this->tenant();

        return view('admin.reminder-settings.index', ['tenant' => $tenant, 'setting' => TenantSetting::forTenant($tenant->id)]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $tenant = $this->tenant();

        $rules = ['overdue_reminders_enabled' => 'nullable|boolean'];
        $messages = [];
        foreach (self::RANGES as $field => [$min, $max]) {
            $rules[$field] = "required|integer|min:{$min}|max:{$max}";
            foreach (['required', 'integer', 'min', 'max'] as $rule) {
                $messages["{$field}.{$rule}"] = "Enter a whole number from {$min} to {$max}."; // section 9
            }
        }
        $data = $request->validate($rules, $messages);

        $setting = TenantSetting::forTenant($tenant->id);
        $old     = $setting->only(['overdue_reminders_enabled', 'overdue_first_after_days', 'overdue_repeat_days', 'overdue_max_reminders']);

        $setting->update([
            'overdue_reminders_enabled' => $request->boolean('overdue_reminders_enabled'),
            'overdue_first_after_days'  => $data['overdue_first_after_days'],
            'overdue_repeat_days'       => $data['overdue_repeat_days'],
            'overdue_max_reminders'     => $data['overdue_max_reminders'],
        ]);

        $audit->log('settings.reminders_changed', [
            'tenant_id' => $tenant->id, 'item_type' => 'tenant_setting', 'item_id' => $setting->id,
            'old' => $old,
            'new' => $setting->only(['overdue_reminders_enabled', 'overdue_first_after_days', 'overdue_repeat_days', 'overdue_max_reminders']),
        ]);

        // 6.10: a change applies from the next day's run, because the job reads the settings each day.
        return redirect()->route('admin.reminder-settings.edit')->with('success', 'Reminder settings saved.');
    }
}
