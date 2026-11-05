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
        return view('auth.field-login', compact('tenant'));
    }

    public function showTeamLead(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        return view('auth.field-login-team-lead', compact('tenant'));
    }
}