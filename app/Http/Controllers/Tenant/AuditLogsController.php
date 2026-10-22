<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogsController extends Controller
{
    private const ACTION_LABELS = [
        'auth'    => 'Sign-in',
        'user'    => 'Users',
        'role'    => 'Roles',
        'company' => 'Company',
        'plan'    => 'Plans',
    ];

    public function index(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $logs = ActivityLog::query()
            ->where('tenant_id', $tenant->id)
            ->when($request->filled('from'),    fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'),      fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('actor'),   fn ($q) => $q->where('actor_id', $request->integer('actor')))
            ->when($request->filled('action'),  fn ($q) => $q->where('action', 'like', $request->input('action') . '%'))
            ->when($request->filled('item'),    fn ($q) => $q->where('item_type', 'like', '%' . $request->input('item')))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $actors = User::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('tenant.audit.index', [
            'logs'         => $logs,
            'actors'       => $actors,
            'actionGroups' => self::ACTION_LABELS,
        ]);
    }
}