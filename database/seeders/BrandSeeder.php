<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Maruti Suzuki',
                'vehicle_type' => ['4 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
            [
                'name' => 'Tata Motors',
                'vehicle_type' => ['4 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
            [
                'name' => 'Mahindra & Mahindra',
                'vehicle_type' => ['4 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
            [
                'name' => 'Hyundai',
                'vehicle_type' => ['4 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
            [
                'name' => 'Toyota',
                'vehicle_type' => ['4 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
            [
                'name' => 'Honda Cars & 2W',
                'vehicle_type' => ['2 Wheeler', '4 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
            [
                'name' => 'Royal Enfield',
                'vehicle_type' => ['2 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
            [
                'name' => 'BMW Motorrad & Cars',
                'vehicle_type' => ['2 Wheeler', '4 Wheeler'],
                'logo' => null,
                'status' => 'Active',
            ],
        ];

        foreach ($brands as $item) {
            Brand::firstOrCreate(['name' => $item['name']], $item);
        }
    }
}
