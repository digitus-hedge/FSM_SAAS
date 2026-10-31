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
                'key'            => 'dm_sans',
                'name'           => 'DM Sans + Figtree',
                'google_param'   => 'DM+Sans:wght@400;500;700&family=Figtree:wght@600;700',
                'body_family'    => "'DM Sans'",
                'display_family' => "'Figtree'",
            ],
            [
                'key'            => 'inter',
                'name'           => 'Inter',
                'google_param'   => 'Inter:wght@400;500;600;700;800',
                'body_family'    => "'Inter'",
                'display_family' => "'Inter'",
            ],
            [
                'key'            => 'plus_jakarta',
                'name'           => 'Plus Jakarta Sans',
                'google_param'   => 'Plus+Jakarta+Sans:wght@400;500;700;800',
                'body_family'    => "'Plus Jakarta Sans'",
                'display_family' => "'Plus Jakarta Sans'",
            ],
            [
                'key'            => 'ibm_plex',
                'name'           => 'IBM Plex Sans',
                'google_param'   => 'IBM+Plex+Sans:wght@400;500;600;700',
                'body_family'    => "'IBM Plex Sans'",
                'display_family' => "'IBM Plex Sans'",
            ],
        ];

        foreach ($fonts as $font) {
            BrandFont::updateOrCreate(['key' => $font['key']], $font);
        }
    }
}