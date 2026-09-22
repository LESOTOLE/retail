<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun demo untuk tiap persona pada PRD bagian 2.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Owner MotoVault',
                'email' => 'admin@motovault.test',
                'phone' => '081200000001',
                'role' => UserRole::Admin,
            ],
            [
                'name' => 'Kasir Toko Pusat',
                'email' => 'staff@motovault.test',
                'phone' => '081200000002',
                'role' => UserRole::Staff,
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'customer@motovault.test',
                'phone' => '081200000003',
                'role' => UserRole::Customer,
            ],
        ];

        $defaultPassword = env('SEED_DEFAULT_PASSWORD', app()->isProduction() ? \Illuminate\Support\Str::random(24) : 'password');

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'password' => Hash::make($defaultPassword),
                    'email_verified_at' => now(),
                ])
            );

            $user->syncRoles([$data['role']->value]);
        }
    }
}
