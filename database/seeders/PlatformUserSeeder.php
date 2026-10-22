<?php

namespace Database\Seeders;

use App\Models\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformUserSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // 2FA is set up on first sign-in via the EnsurePlatformTwoFactor middleware.
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => null,
        ];

        PlatformUser::updateOrCreate(
            ['email' => 'owner@aiywah.app'],
            $defaults + [
                'name'          => 'Platform Owner',
                'password'      => Hash::make('Aiywah#2026'),
                'platform_role' => 'owner',
            ],
        );

        PlatformUser::updateOrCreate(
            ['email' => 'support@aiywah.app'],
            $defaults + [
                'name'          => 'Platform Support',
                'password'      => Hash::make('Aiywah#2026'),
                'platform_role' => 'support',
            ],
        );

        PlatformUser::updateOrCreate(
            ['email' => 'finance@aiywah.app'],
            $defaults + [
                'name'          => 'Platform Finance',
                'password'      => Hash::make('Aiywah#2026'),
                'platform_role' => 'finance',
            ],
        );
    }
}