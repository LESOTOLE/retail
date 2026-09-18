<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Seeder master kendaraan: motor populer di Indonesia (PRD Task 2).
 */
class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            // Honda
            ['brand' => 'Honda', 'model' => 'Vario 125', 'year_start' => 2018, 'year_end' => null],
            ['brand' => 'Honda', 'model' => 'Vario 160', 'year_start' => 2022, 'year_end' => null],
            ['brand' => 'Honda', 'model' => 'BeAT', 'year_start' => 2020, 'year_end' => null],
            ['brand' => 'Honda', 'model' => 'Scoopy', 'year_start' => 2020, 'year_end' => null],
            ['brand' => 'Honda', 'model' => 'PCX 160', 'year_start' => 2021, 'year_end' => null],
            ['brand' => 'Honda', 'model' => 'CBR150R', 'year_start' => 2021, 'year_end' => null],
            ['brand' => 'Honda', 'model' => 'Supra X 125', 'year_start' => 2016, 'year_end' => 2023],

            // Yamaha
            ['brand' => 'Yamaha', 'model' => 'NMAX 155', 'year_start' => 2020, 'year_end' => null],
            ['brand' => 'Yamaha', 'model' => 'Aerox 155', 'year_start' => 2021, 'year_end' => null],
            ['brand' => 'Yamaha', 'model' => 'Mio M3 125', 'year_start' => 2015, 'year_end' => null],
            ['brand' => 'Yamaha', 'model' => 'R15 V4', 'year_start' => 2022, 'year_end' => null],
            ['brand' => 'Yamaha', 'model' => 'Jupiter MX King 150', 'year_start' => 2015, 'year_end' => 2023],

            // Kawasaki
            ['brand' => 'Kawasaki', 'model' => 'Ninja 250', 'year_start' => 2018, 'year_end' => null],
            ['brand' => 'Kawasaki', 'model' => 'W175', 'year_start' => 2017, 'year_end' => null],

            // Suzuki
            ['brand' => 'Suzuki', 'model' => 'Satria F150', 'year_start' => 2016, 'year_end' => null],
            ['brand' => 'Suzuki', 'model' => 'Nex II', 'year_start' => 2018, 'year_end' => null],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::updateOrCreate(
                [
                    'brand' => $vehicle['brand'],
                    'model' => $vehicle['model'],
                    'year_start' => $vehicle['year_start'],
                ],
                $vehicle
            );
        }
    }
}
