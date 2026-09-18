<?php

namespace Database\Seeders;

use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'code' => 'WH-JKT-SEL',
                'name' => 'Central Warehouse Jakarta Selatan',
                'address' => 'Jl. SCBD Senopati No. 88, Kebayoran Baru',
                'city' => 'Jakarta Selatan',
                'postal_code' => '12190',
                'latitude' => -6.229728,
                'longitude' => 106.807490,
                'is_active' => true,
                'is_central' => true,
            ],
            [
                'code' => 'WH-BDG-DAGO',
                'name' => 'Branch Warehouse Bandung Dago',
                'address' => 'Jl. Ir. H. Juanda No. 120, Coblong',
                'city' => 'Bandung',
                'postal_code' => '40132',
                'latitude' => -6.890432,
                'longitude' => 107.616235,
                'is_active' => true,
                'is_central' => false,
            ],
            [
                'code' => 'WH-SBY-GBG',
                'name' => 'Branch Warehouse Surabaya Gubeng',
                'address' => 'Jl. Pemuda No. 45, Gubeng',
                'city' => 'Surabaya',
                'postal_code' => '60271',
                'latitude' => -7.265757,
                'longitude' => 112.752090,
                'is_active' => true,
                'is_central' => false,
            ],
        ];

        foreach ($warehouses as $data) {
            $warehouse = Warehouse::updateOrCreate(['code' => $data['code']], $data);

            // Seed stocks for all product variants
            $variants = ProductVariant::all();
            foreach ($variants as $variant) {
                // Central warehouse has full stock, branches have standard stock
                $qty = $warehouse->is_central
                    ? max(20, $variant->stock)
                    : max(5, (int) ($variant->stock / 2));

                WarehouseStock::updateOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'stock' => $qty,
                        'min_stock_alert' => $variant->min_stock_alert ?: 3,
                    ]
                );
            }
        }
    }
}
