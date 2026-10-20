<?php

namespace Database\Seeders;

use App\Models\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformUserSeeder extends Seeder
{
    public function run(): void
    {
        PlatformUser::updateOrCreate(
            ['email' => 'owner@aiywah.app'],
            [
                'name'          => 'Platform Owner',
                'password'      => Hash::make('Aiywah#2026'),   // matches demo-creds doc
                'platform_role' => 'owner',
                // Mandatory 2FA per D8. The demo authenticator code is 123456
                // (fake TOTP in local/dev, real secret in prod).
                'two_factor_secret' => encrypt('DEMO123456FAKESECRET'),
                'two_factor_confirmed_at' => now(),
            ],
        );

        PlatformUser::updateOrCreate(
            ['email' => 'support@aiywah.app'],
            [
                'name'          => 'Platform Support',
                'password'      => Hash::make('Aiywah#2026'),
                'platform_role' => 'support',
                'two_factor_secret' => encrypt('DEMO123456FAKESECRET'),
                'two_factor_confirmed_at' => now(),
            ],
        );

        PlatformUser::updateOrCreate(
            ['email' => 'finance@aiywah.app'],
            [
                'name'          => 'Platform Finance',
                'password'      => Hash::make('Aiywah#2026'),
                'platform_role' => 'finance',
                'two_factor_secret' => encrypt('DEMO123456FAKESECRET'),
                'two_factor_confirmed_at' => now(),
            ],
        );
    }
}