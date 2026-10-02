<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

class VehicleModelSeeder extends Seeder
{
    public function run(): void
    {
        $maruti = Brand::where('name', 'like', '%Maruti%')->first();
        $tata = Brand::where('name', 'like', '%Tata%')->first();
        $mahindra = Brand::where('name', 'like', '%Mahindra%')->first();
        $hyundai = Brand::where('name', 'like', '%Hyundai%')->first();
        $toyota = Brand::where('name', 'like', '%Toyota%')->first();
        $honda = Brand::where('name', 'like', '%Honda%')->first();
        $enfield = Brand::where('name', 'like', '%Royal Enfield%')->first();

        $models = [];

        if ($maruti) {
            $models[] = ['name' => 'Brezza', 'brand_id' => $maruti->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Grand Vitara', 'brand_id' => $maruti->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Fronx', 'brand_id' => $maruti->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Swift', 'brand_id' => $maruti->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
        }

        if ($tata) {
            $models[] = ['name' => 'Nexon', 'brand_id' => $tata->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Safari', 'brand_id' => $tata->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Harrier', 'brand_id' => $tata->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Punch', 'brand_id' => $tata->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
        }

        if ($mahindra) {
            $models[] = ['name' => 'Scorpio-N', 'brand_id' => $mahindra->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'XUV700', 'brand_id' => $mahindra->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Thar ROXX', 'brand_id' => $mahindra->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
        }

        if ($hyundai) {
            $models[] = ['name' => 'Creta', 'brand_id' => $hyundai->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Venue', 'brand_id' => $hyundai->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
        }

        if ($toyota) {
            $models[] = ['name' => 'Innova Hycross', 'brand_id' => $toyota->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Fortuner', 'brand_id' => $toyota->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
        }

        if ($enfield) {
            $models[] = ['name' => 'Classic 350', 'brand_id' => $enfield->id, 'vehicle_segment' => '2 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Hunter 350', 'brand_id' => $enfield->id, 'vehicle_segment' => '2 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Himalayan 450', 'brand_id' => $enfield->id, 'vehicle_segment' => '2 Wheeler', 'status' => 'Active'];
        }

        if ($honda) {
            $models[] = ['name' => 'Activa 6G', 'brand_id' => $honda->id, 'vehicle_segment' => '2 Wheeler', 'status' => 'Active'];
            $models[] = ['name' => 'Elevate', 'brand_id' => $honda->id, 'vehicle_segment' => '4 Wheeler', 'status' => 'Active'];
        }

        foreach ($models as $item) {
            VehicleModel::firstOrCreate(['name' => $item['name'], 'brand_id' => $item['brand_id']], $item);
        }
    }
}
