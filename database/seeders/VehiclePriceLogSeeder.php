<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\User;
use App\Models\VehicleModel;
use App\Models\VehiclePriceLog;
use App\Models\VehicleVariant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class VehiclePriceLogSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'alexander.vance@carcrm.com')->first() ?? User::first();

        // 1. Maruti Suzuki Grand Vitara Alpha+ Strong Hybrid
        $vitara = VehicleModel::where('name', 'like', '%Grand Vitara%')->first();
        $vitaraVariant = VehicleVariant::where('name', 'like', '%Alpha+%')->first();
        if ($vitaraVariant) {
            $vitaraVariant->update([
                'ex_showroom_price' => 1999000.00,
                'rto_road_tax' => 199900.00,
                'insurance' => 68000.00,
                'fastag_logistics' => 2500.00,
                'on_road_price' => 2269400.00,
            ]);
        }

        // 2. Tata Motors Safari Adventure Plus Dark Edition
        $safari = VehicleModel::where('name', 'like', '%Safari%')->first();
        $safariVariant = VehicleVariant::where('name', 'like', '%Adventure Plus%')->first();
        if ($safariVariant) {
            $safariVariant->update([
                'ex_showroom_price' => 2619000.00,
                'rto_road_tax' => 261900.00,
                'insurance' => 78500.00,
                'fastag_logistics' => 2500.00,
                'on_road_price' => 2961900.00,
            ]);
        }

        // Create Seed Audit Logs
        $logs = [
            [
                'variant_id' => $safariVariant?->id ?? 1,
                'model_id' => $safari?->id ?? 1,
                'brand_id' => $safari?->brand_id ?? 1,
                'model_variant_name' => 'Adventure Plus Dark Edition (Safari)',
                'brand_name' => 'Tata Motors',
                'previous_ex_showroom' => 2580000.00,
                'revised_ex_showroom' => 2619000.00,
                'net_difference' => 39000.00,
                'rto_road_tax' => 261900.00,
                'insurance' => 78500.00,
                'fastag_logistics' => 2500.00,
                'previous_on_road' => 2922900.00,
                'revised_on_road' => 2961900.00,
                'updated_by_id' => $user?->id,
                'updated_by_name' => 'Alexander Vance',
                'revision_date' => '2026-01-28',
                'status' => 'Active',
            ],
            [
                'variant_id' => $vitaraVariant?->id ?? 1,
                'model_id' => $vitara?->id ?? 1,
                'brand_id' => $vitara?->brand_id ?? 1,
                'model_variant_name' => 'Alpha+ Strong Hybrid (Grand Vitara)',
                'brand_name' => 'Maruti Suzuki',
                'previous_ex_showroom' => 1975000.00,
                'revised_ex_showroom' => 1999000.00,
                'net_difference' => 24000.00,
                'rto_road_tax' => 199900.00,
                'insurance' => 68000.00,
                'fastag_logistics' => 2500.00,
                'previous_on_road' => 2243000.00,
                'revised_on_road' => 2269400.00,
                'updated_by_id' => $user?->id,
                'updated_by_name' => 'Alexander Vance',
                'revision_date' => '2026-01-22',
                'status' => 'Active',
            ],
            [
                'variant_id' => 1,
                'model_id' => 1,
                'brand_id' => 1,
                'model_variant_name' => 'AX7L 4x4 AT (Thar Roxx)',
                'brand_name' => 'Mahindra',
                'previous_ex_showroom' => 2210000.00,
                'revised_ex_showroom' => 2249000.00,
                'net_difference' => 39000.00,
                'rto_road_tax' => 224900.00,
                'insurance' => 71000.00,
                'fastag_logistics' => 2500.00,
                'previous_on_road' => 2508400.00,
                'revised_on_road' => 2547400.00,
                'updated_by_id' => $user?->id,
                'updated_by_name' => 'Alexander Vance',
                'revision_date' => '2026-01-15',
                'status' => 'Active',
            ],
            [
                'variant_id' => 1,
                'model_id' => 1,
                'brand_id' => 1,
                'model_variant_name' => 'Legender 4x4 AT (Fortuner)',
                'brand_name' => 'Toyota Kirloskar',
                'previous_ex_showroom' => 4720000.00,
                'revised_ex_showroom' => 4800000.00,
                'net_difference' => 80000.00,
                'rto_road_tax' => 480000.00,
                'insurance' => 125000.00,
                'fastag_logistics' => 2500.00,
                'previous_on_road' => 5327500.00,
                'revised_on_road' => 5407500.00,
                'updated_by_id' => $user?->id,
                'updated_by_name' => 'Alexander Vance',
                'revision_date' => '2026-01-10',
                'status' => 'Active',
            ],
        ];

        foreach ($logs as $log) {
            VehiclePriceLog::create($log);
        }
    }
}
