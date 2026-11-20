<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** The platform console's colour and font (the settings popup of the theme dock). Saved for the signed-in platform user. */
class BrandingController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_color' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_key'    => ['required', 'string', 'exists:brand_fonts,key'],
        ]);

        $user = auth('platform')->user();
        $user->forceFill($data)->save();

        return back()->with('success', 'Platform branding updated.');
    }
}
