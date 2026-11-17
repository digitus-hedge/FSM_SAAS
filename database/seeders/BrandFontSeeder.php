<?php

namespace Database\Seeders;

use App\Models\BrandFont;
use Illuminate\Database\Seeder;

class BrandFontSeeder extends Seeder
{
    public function run(): void
    {
        $fonts = [
            [
                // Safest choice: excellent at small sizes on a phone in sunlight.
                'key'            => 'inter',
                'name'           => 'Inter',
                'google_param'   => 'Inter:wght@400;500;600;700;800',
                'body_family'    => "'Inter'",
                'display_family' => "'Inter'",
            ],
            [
                // Clean and slightly geometric; sharp numerals in KPI cards.
                'key'            => 'manrope',
                'name'           => 'Manrope',
                'google_param'   => 'Manrope:wght@400;500;600;700;800',
                'body_family'    => "'Manrope'",
                'display_family' => "'Manrope'",
            ],
            [
                // Warm and polished; a premium feel for headings and menus.
                'key'            => 'plus_jakarta',
                'name'           => 'Plus Jakarta Sans',
                'google_param'   => 'Plus+Jakarta+Sans:wght@400;500;600;700;800',
                'body_family'    => "'Plus Jakarta Sans'",
                'display_family' => "'Plus Jakarta Sans'",
            ],
            [
                // Crisp and technical; very clear figures in tables.
                'key'            => 'geist',
                'name'           => 'Geist',
                'google_param'   => 'Geist:wght@400;500;600;700',
                'body_family'    => "'Geist'",
                'display_family' => "'Geist'",
            ],
        ];

        foreach ($fonts as $font) {
            BrandFont::updateOrCreate(['key' => $font['key']], $font + ['is_active' => true]);
        }

        // Old choices stay in the table (tenants may still use them and DM Sans is the
        // fallback default) but are hidden from the Branding picker.
        BrandFont::whereIn('key', ['dm_sans', 'ibm_plex'])->update(['is_active' => false]);
    }
}
