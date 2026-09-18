<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder 10 produk realistis lengkap dengan varian ber-SKU dan
 * pivot kompatibilitas kendaraan (PRD Task 2).
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->products() as $data) {
            $category = Category::where('slug', $data['category_slug'])->first();

            if (! $category) {
                continue;
            }

            $product = Product::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'brand' => $data['brand'],
                    'description' => $data['description'],
                    'base_price' => $data['base_price'],
                    'is_active' => true,
                ]
            );

            foreach ($data['variants'] as $variant) {
                $product->variants()->updateOrCreate(
                    ['sku' => $variant['sku']],
                    $variant
                );
            }

            $this->syncVehicles($product, $data['vehicles']);
        }
    }

    /**
     * Menautkan produk ke kendaraan berdasarkan nama model.
     *
     * @param  array<string, string>  $vehicleNotes  [model => notes]
     */
    protected function syncVehicles(Product $product, array $vehicleNotes): void
    {
        $pivot = [];

        foreach ($vehicleNotes as $model => $notes) {
            $vehicle = Vehicle::where('model', $model)->first();

            if ($vehicle) {
                $pivot[$vehicle->id] = ['notes' => $notes];
            }
        }

        if ($pivot !== []) {
            $product->vehicles()->sync($pivot);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function products(): array
    {
        return array_merge($this->lubricantAndIgnition(), $this->electricalAndHelmets(), $this->brakesAndTires());
    }

    /**
     * Kategori Oli & Cairan dan Busi.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function lubricantAndIgnition(): array
    {
        return [
            [
                'name' => 'Motul Scooter Power LE 10W-40',
                'brand' => 'Motul',
                'category_slug' => 'oli-cairan',
                'base_price' => 115000,
                'description' => 'Oli mesin full sintetik khusus skutik dengan teknologi Ester Core. Menjaga performa CVT dan mesin tetap halus pada putaran tinggi serta tahan terhadap panas kemacetan kota.',
                'variants' => [
                    ['sku' => 'MOT-10W40-08L', 'variant_name' => '10W-40 0.8L', 'additional_price' => 0, 'stock' => 32, 'min_stock_alert' => 8],
                    ['sku' => 'MOT-10W40-1L', 'variant_name' => '10W-40 1L', 'additional_price' => 18000, 'stock' => 14, 'min_stock_alert' => 6],
                ],
                'vehicles' => [
                    'Vario 125' => 'Plug and play, kapasitas 0.8L',
                    'Vario 160' => 'Plug and play, kapasitas 0.8L',
                    'BeAT' => 'Plug and play, kapasitas 0.65L',
                    'NMAX 155' => 'Gunakan varian 1L untuk sekali ganti',
                    'Aerox 155' => 'Gunakan varian 1L untuk sekali ganti',
                    'PCX 160' => 'Plug and play',
                ],
            ],
            [
                'name' => 'Federal Oil Matic Gear Oil',
                'brand' => 'Federal',
                'category_slug' => 'oli-cairan',
                'base_price' => 28000,
                'description' => 'Oli gardan khusus transmisi CVT skutik. Melindungi gear reduksi dari keausan dan mengurangi suara kasar pada kecepatan rendah.',
                'variants' => [
                    ['sku' => 'FED-GEAR-120ML', 'variant_name' => 'Gardan 120ml', 'additional_price' => 0, 'stock' => 55, 'min_stock_alert' => 12],
                    ['sku' => 'FED-GEAR-180ML', 'variant_name' => 'Gardan 180ml', 'additional_price' => 9000, 'stock' => 3, 'min_stock_alert' => 10],
                ],
                'vehicles' => [
                    'BeAT' => 'Plug and play, 100ml per penggantian',
                    'Scoopy' => 'Plug and play',
                    'Vario 125' => 'Plug and play',
                    'Vario 160' => 'Plug and play',
                    'NMAX 155' => 'Gunakan varian 180ml',
                    'PCX 160' => 'Gunakan varian 180ml',
                    'Mio M3 125' => 'Plug and play',
                ],
            ],
            [
                'name' => 'NGK Busi Iridium CPR9EAIX-9',
                'brand' => 'NGK',
                'category_slug' => 'busi',
                'base_price' => 98000,
                'description' => 'Busi iridium dengan center electrode 0.6mm untuk pengapian lebih fokus, akselerasi responsif, dan umur pakai hingga 4x busi standar.',
                'variants' => [
                    ['sku' => 'NGK-CPR9EAIX-9', 'variant_name' => 'CPR9EAIX-9', 'additional_price' => 0, 'stock' => 25, 'min_stock_alert' => 5],
                    ['sku' => 'NGK-CPR8EAIX-9', 'variant_name' => 'CPR8EAIX-9', 'additional_price' => 0, 'stock' => 18, 'min_stock_alert' => 5],
                ],
                'vehicles' => [
                    'Vario 160' => 'Kode busi CPR9EAIX-9 sesuai standar pabrikan',
                    'PCX 160' => 'Kode busi CPR9EAIX-9 sesuai standar pabrikan',
                    'CBR150R' => 'Gunakan varian CPR9EAIX-9',
                    'Vario 125' => 'Gunakan varian CPR8EAIX-9',
                    'BeAT' => 'Gunakan varian CPR8EAIX-9',
                ],
            ],
            [
                'name' => 'Denso Busi Iridium Power IU24',
                'brand' => 'Denso',
                'category_slug' => 'busi',
                'base_price' => 89000,
                'description' => 'Busi iridium 0.4mm dengan U-groove ground electrode. Memberikan pembakaran lebih sempurna dan start mesin lebih mudah saat kondisi dingin.',
                'variants' => [
                    ['sku' => 'DEN-IU24-STD', 'variant_name' => 'IU24 Standard', 'additional_price' => 0, 'stock' => 22, 'min_stock_alert' => 6],
                ],
                'vehicles' => [
                    'NMAX 155' => 'Plug and play, pengganti busi standar',
                    'Aerox 155' => 'Plug and play',
                    'R15 V4' => 'Plug and play',
                    'Satria F150' => 'Butuh pengecekan panjang ulir',
                ],
            ],
        ];
    }

    /**
     * Kategori Aki & Kelistrikan dan Helm.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function electricalAndHelmets(): array
    {
        return [
            [
                'name' => 'GS Astra Aki Kering GTZ-5S',
                'brand' => 'GS Astra',
                'category_slug' => 'aki-kelistrikan',
                'base_price' => 245000,
                'description' => 'Aki kering MF (maintenance free) 12V dengan terminal standar motor injeksi. Tidak perlu penambahan air aki dan tahan getaran.',
                'variants' => [
                    ['sku' => 'GS-GTZ5S-12V', 'variant_name' => 'GTZ-5S 12V 3.5Ah', 'additional_price' => 0, 'stock' => 20, 'min_stock_alert' => 5],
                    ['sku' => 'GS-GTZ6V-12V', 'variant_name' => 'GTZ-6V 12V 5Ah', 'additional_price' => 65000, 'stock' => 4, 'min_stock_alert' => 5],
                ],
                'vehicles' => [
                    'BeAT' => 'Plug and play, soket standar',
                    'Scoopy' => 'Plug and play, soket standar',
                    'Vario 125' => 'Plug and play',
                    'Mio M3 125' => 'Plug and play',
                    'Vario 160' => 'Gunakan varian GTZ-6V (5Ah)',
                    'NMAX 155' => 'Gunakan varian GTZ-6V (5Ah)',
                ],
            ],
            [
                'name' => 'Yuasa Aki Kering YTZ7V',
                'brand' => 'Yuasa',
                'category_slug' => 'aki-kelistrikan',
                'base_price' => 320000,
                'description' => 'Aki kering premium 12V 6Ah dengan CCA tinggi, cocok untuk motor dengan beban kelistrikan besar seperti skutik 155cc ke atas.',
                'variants' => [
                    ['sku' => 'YUA-YTZ7V-6AH', 'variant_name' => 'YTZ7V 12V 6Ah', 'additional_price' => 0, 'stock' => 12, 'min_stock_alert' => 4],
                ],
                'vehicles' => [
                    'NMAX 155' => 'Plug and play, rekomendasi pabrikan',
                    'Aerox 155' => 'Plug and play',
                    'PCX 160' => 'Plug and play',
                    'Vario 160' => 'Butuh penyesuaian dudukan tipis',
                ],
            ],
            [
                'name' => 'KYT TT-Course Helm Full Face',
                'brand' => 'KYT',
                'category_slug' => 'helm',
                'base_price' => 1150000,
                'description' => 'Helm full face berstandar SNI & DOT dengan shell fiberglass, double visor, dan ventilasi aerodinamis. Cocok untuk harian maupun touring.',
                'variants' => [
                    ['sku' => 'KYT-TTC-M', 'variant_name' => 'Ukuran M', 'additional_price' => 0, 'stock' => 9, 'min_stock_alert' => 3],
                    ['sku' => 'KYT-TTC-L', 'variant_name' => 'Ukuran L', 'additional_price' => 0, 'stock' => 7, 'min_stock_alert' => 3],
                    ['sku' => 'KYT-TTC-XL', 'variant_name' => 'Ukuran XL', 'additional_price' => 50000, 'stock' => 3, 'min_stock_alert' => 3],
                ],
                'vehicles' => [
                    'CBR150R' => 'Universal, tidak terikat tipe motor',
                    'R15 V4' => 'Universal, tidak terikat tipe motor',
                    'Ninja 250' => 'Universal, tidak terikat tipe motor',
                ],
            ],
            [
                'name' => 'NHK R6 Helm Half Face',
                'brand' => 'NHK',
                'category_slug' => 'helm',
                'base_price' => 380000,
                'description' => 'Helm half face ringan dengan visor anti gores dan busa pipi yang dapat dilepas untuk dicuci. Ideal untuk penggunaan harian dalam kota.',
                'variants' => [
                    ['sku' => 'NHK-R6-M', 'variant_name' => 'Ukuran M', 'additional_price' => 0, 'stock' => 16, 'min_stock_alert' => 5],
                    ['sku' => 'NHK-R6-L', 'variant_name' => 'Ukuran L', 'additional_price' => 0, 'stock' => 11, 'min_stock_alert' => 5],
                    ['sku' => 'NHK-R6-XL', 'variant_name' => 'Ukuran XL', 'additional_price' => 25000, 'stock' => 2, 'min_stock_alert' => 5],
                ],
                'vehicles' => [
                    'BeAT' => 'Universal',
                    'Scoopy' => 'Universal',
                    'Mio M3 125' => 'Universal',
                    'Nex II' => 'Universal',
                ],
            ],
        ];
    }

    /**
     * Kategori Pengereman dan Ban & Velg.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function brakesAndTires(): array
    {
        return [
            [
                'name' => 'Aspira Kampas Rem Cakram Depan',
                'brand' => 'Aspira',
                'category_slug' => 'pengereman',
                'base_price' => 68000,
                'description' => 'Kampas rem cakram material semi-metallic dengan daya cengkeram stabil di kondisi basah maupun kering, minim bunyi decit.',
                'variants' => [
                    ['sku' => 'ASP-BRK-VAR160', 'variant_name' => 'Tipe Vario/PCX', 'additional_price' => 0, 'stock' => 28, 'min_stock_alert' => 8],
                    ['sku' => 'ASP-BRK-NMAX', 'variant_name' => 'Tipe NMAX/Aerox', 'additional_price' => 7000, 'stock' => 21, 'min_stock_alert' => 8],
                ],
                'vehicles' => [
                    'Vario 160' => 'Plug and play, dudukan standar',
                    'Vario 125' => 'Plug and play, dudukan standar',
                    'PCX 160' => 'Plug and play',
                    'NMAX 155' => 'Gunakan varian tipe NMAX/Aerox',
                    'Aerox 155' => 'Gunakan varian tipe NMAX/Aerox',
                ],
            ],
            [
                'name' => 'Brembo Minyak Rem DOT 4',
                'brand' => 'Brembo',
                'category_slug' => 'pengereman',
                'base_price' => 55000,
                'description' => 'Minyak rem DOT 4 dengan titik didih tinggi, mencegah vapor lock saat pengereman berulang di jalan menurun.',
                'variants' => [
                    ['sku' => 'BRE-DOT4-250ML', 'variant_name' => 'DOT 4 250ml', 'additional_price' => 0, 'stock' => 40, 'min_stock_alert' => 10],
                    ['sku' => 'BRE-DOT4-500ML', 'variant_name' => 'DOT 4 500ml', 'additional_price' => 42000, 'stock' => 5, 'min_stock_alert' => 6],
                ],
                'vehicles' => [
                    'Vario 160' => 'Universal untuk sistem rem hidrolik',
                    'NMAX 155' => 'Universal untuk sistem rem hidrolik',
                    'CBR150R' => 'Universal',
                    'Ninja 250' => 'Universal',
                    'Satria F150' => 'Universal',
                ],
            ],
            [
                'name' => 'IRC Ban Tubeless Road Winner',
                'brand' => 'IRC',
                'category_slug' => 'ban-velg',
                'base_price' => 265000,
                'description' => 'Ban tubeless dengan pola tapak zig-zag untuk pembuangan air optimal. Kompon soft-medium yang seimbang antara grip dan keawetan.',
                'variants' => [
                    ['sku' => 'IRC-RW-8090-14', 'variant_name' => '80/90-14 Depan', 'additional_price' => 0, 'stock' => 15, 'min_stock_alert' => 4],
                    ['sku' => 'IRC-RW-9090-14', 'variant_name' => '90/90-14 Belakang', 'additional_price' => 35000, 'stock' => 13, 'min_stock_alert' => 4],
                    ['sku' => 'IRC-RW-11070-13', 'variant_name' => '110/70-13 Depan', 'additional_price' => 95000, 'stock' => 6, 'min_stock_alert' => 4],
                ],
                'vehicles' => [
                    'BeAT' => 'Ring 14, gunakan 80/90 depan & 90/90 belakang',
                    'Vario 125' => 'Ring 14, plug and play',
                    'Scoopy' => 'Ring 12, butuh pengecekan ukuran velg',
                    'NMAX 155' => 'Ring 13, gunakan varian 110/70-13',
                    'Aerox 155' => 'Ring 14 depan, cek ukuran sebelum beli',
                ],
            ],
            [
                'name' => 'Nemo Windshield Visor Universal',
                'brand' => 'Nemo',
                'category_slug' => 'aksesori-body',
                'base_price' => 175000,
                'description' => 'Visor pelindung angin berbahan akrilik tebal 3mm dengan bracket stainless. Mengurangi terpaan angin pada kecepatan tinggi.',
                'variants' => [
                    ['sku' => 'NEM-WS-SMOKE', 'variant_name' => 'Smoke Transparan', 'additional_price' => 0, 'stock' => 10, 'min_stock_alert' => 3],
                    ['sku' => 'NEM-WS-CLEAR', 'variant_name' => 'Clear Bening', 'additional_price' => 0, 'stock' => 8, 'min_stock_alert' => 3],
                ],
                'vehicles' => [
                    'NMAX 155' => 'Plug and play pada dudukan bawaan',
                    'PCX 160' => 'Plug and play pada dudukan bawaan',
                    'Vario 160' => 'Butuh bracket tambahan',
                    'Aerox 155' => 'Butuh bracket tambahan',
                ],
            ],
        ];
    }
}
