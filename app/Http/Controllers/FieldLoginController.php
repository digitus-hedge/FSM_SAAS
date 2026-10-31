<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class FieldLoginController extends Controller
{
    public function showTechnician(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        return view('auth.login-technician', compact('tenant'));
    }

    public function showTeamLead(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        return view('auth.login-team-lead', compact('tenant'));
    }
}