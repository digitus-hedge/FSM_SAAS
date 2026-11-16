<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserTimeOff;
use App\Services\Audit\AuditLogger;
use App\Services\Dispatch\TechnicianSuggester;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimeOffController extends Controller
{
    public function __construct(private TechnicianSuggester $suggester, private AuditLogger $audit) {}

    public function index()
    {
        $user = auth()->user();
        abort_unless($user->canDo('sr.assign'), 403);
        $tenantId = (int) $user->tenant_id;

        return view('admin.dispatch.time-off', [
            'techs'   => $this->suggester->candidates($tenantId),
            'entries' => UserTimeOff::with('user:id,name')
                ->where('tenant_id', $tenantId)
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->get(),
            'types'   => UserTimeOff::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->canDo('sr.assign'), 403);
        $tenantId = (int) $user->tenant_id;
        $allowed  = $this->suggester->candidates($tenantId)->pluck('id')->all();

        $data = $request->validate([
            'user_id'   => ['required', 'integer', Rule::in($allowed)],
            'type'      => ['required', Rule::in(array_keys(UserTimeOff::TYPES))],
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
            'note'      => ['nullable', 'string', 'max:255'],
        ], [
            'user_id.in'     => 'Choose a technician or team lead from the list.',
            'ends_at.after'  => 'The end must be after the start.',
        ]);

        $row = UserTimeOff::create($data + ['tenant_id' => $tenantId, 'created_by_user_id' => $user->id]);

        $this->audit->log('dispatch.time_off_added', [
            'item_type' => UserTimeOff::class,
            'item_id'   => $row->id,
            'new'       => $row->only(['user_id', 'type', 'starts_at', 'ends_at']),
        ]);

        return redirect()->route('dispatch.time-off')->with('success', 'Time off saved.');
    }

    public function destroy(int $id)
    {
        $user = auth()->user();
        abort_unless($user->canDo('sr.assign'), 403);

        $row = UserTimeOff::where('tenant_id', $user->tenant_id)->findOrFail($id);
        $this->audit->log('dispatch.time_off_removed', [
            'item_type' => UserTimeOff::class,
            'item_id'   => $row->id,
            'old'       => $row->only(['user_id', 'type', 'starts_at', 'ends_at']),
        ]);
        $row->delete();

        return redirect()->route('dispatch.time-off')->with('success', 'Time off removed.');
    }
}
