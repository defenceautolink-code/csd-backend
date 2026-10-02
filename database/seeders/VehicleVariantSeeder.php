<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Seeder;

class VehicleVariantSeeder extends Seeder
{
    public function run(): void
    {
        $swift = VehicleModel::where('name', 'like', '%Swift%')->first();
        $brezza = VehicleModel::where('name', 'like', '%Brezza%')->first();
        $vitara = VehicleModel::where('name', 'like', '%Grand Vitara%')->first();
        $nexon = VehicleModel::where('name', 'like', '%Nexon%')->first();
        $safari = VehicleModel::where('name', 'like', '%Safari%')->first();
        $scorpio = VehicleModel::where('name', 'like', '%Scorpio%')->first();
        $creta = VehicleModel::where('name', 'like', '%Creta%')->first();
        $classic = VehicleModel::where('name', 'like', '%Classic 350%')->first();

        $variants = [];

        if ($swift) {
            $variants[] = ['brand_id' => $swift->brand_id, 'model_id' => $swift->id, 'name' => 'LXi', 'price' => 649000, 'status' => 'Active'];
            $variants[] = ['brand_id' => $swift->brand_id, 'model_id' => $swift->id, 'name' => 'VXi', 'price' => 729000, 'status' => 'Active'];
            $variants[] = ['brand_id' => $swift->brand_id, 'model_id' => $swift->id, 'name' => 'ZXi+', 'price' => 959000, 'status' => 'Active'];
        }

        if ($brezza) {
            $variants[] = ['brand_id' => $brezza->brand_id, 'model_id' => $brezza->id, 'name' => 'ZXi Dual Tone', 'price' => 1149000, 'status' => 'Active'];
            $variants[] = ['brand_id' => $brezza->brand_id, 'model_id' => $brezza->id, 'name' => 'ZXi+ AT', 'price' => 1398000, 'status' => 'Active'];
        }

        if ($vitara) {
            $variants[] = ['brand_id' => $vitara->brand_id, 'model_id' => $vitara->id, 'name' => 'Zeta Hybrid', 'price' => 1829000, 'status' => 'Active'];
            $variants[] = ['brand_id' => $vitara->brand_id, 'model_id' => $vitara->id, 'name' => 'Alpha+ Strong Hybrid', 'price' => 1999000, 'status' => 'Active'];
        }

        if ($nexon) {
            $variants[] = ['brand_id' => $nexon->brand_id, 'model_id' => $nexon->id, 'name' => 'Pure (S)', 'price' => 1029000, 'status' => 'Active'];
            $variants[] = ['brand_id' => $nexon->brand_id, 'model_id' => $nexon->id, 'name' => 'Fearless Plus Dark Edition', 'price' => 1499000, 'status' => 'Active'];
        }

        if ($safari) {
            $variants[] = ['brand_id' => $safari->brand_id, 'model_id' => $safari->id, 'name' => 'Adventure Plus (Dark Edition)', 'price' => 2619000, 'status' => 'Active'];
            $variants[] = ['brand_id' => $safari->brand_id, 'model_id' => $safari->id, 'name' => 'Accomplished+ 6S', 'price' => 2734000, 'status' => 'Active'];
        }

        if ($scorpio) {
            $variants[] = ['brand_id' => $scorpio->brand_id, 'model_id' => $scorpio->id, 'name' => 'Z8 L 4x4', 'price' => 2454000, 'status' => 'Active'];
        }

        if ($creta) {
            $variants[] = ['brand_id' => $creta->brand_id, 'model_id' => $creta->id, 'name' => 'SX (O) Turbo DCT', 'price' => 2015000, 'status' => 'Active'];
        }

        if ($classic) {
            $variants[] = ['brand_id' => $classic->brand_id, 'model_id' => $classic->id, 'name' => 'Redditch Red', 'price' => 193000, 'status' => 'Active'];
            $variants[] = ['brand_id' => $classic->brand_id, 'model_id' => $classic->id, 'name' => 'Stealth Black (Dual ABS)', 'price' => 225000, 'status' => 'Active'];
        }

        foreach ($variants as $item) {
            VehicleVariant::firstOrCreate(['name' => $item['name'], 'model_id' => $item['model_id']], $item);
        }
    }
}
