<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database sesuai urutan dependensi:
     * RBAC -> users -> master kendaraan -> kategori -> produk & pivot.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            VehicleSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            DiagnosticSymptomSeeder::class,
            WarehouseSeeder::class,
        ]);
    }
}
