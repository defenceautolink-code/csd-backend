<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $maruti = Brand::where('name', 'like', '%Maruti%')->first();
        $tata = Brand::where('name', 'like', '%Tata%')->first();
        $mahindra = Brand::where('name', 'like', '%Mahindra%')->first();
        $enfield = Brand::where('name', 'like', '%Royal Enfield%')->first();

        $website = LeadSource::where('title', 'like', '%Website%')->first();
        $walkin = LeadSource::where('title', 'like', '%Walk-in%')->first();
        $facebook = LeadSource::where('title', 'like', '%Facebook%')->first();

        $newStatus = LeadStatus::where('name', 'like', '%New%')->first();
        $followup = LeadStatus::where('name', 'like', '%Follow-Up%')->first();
        $testdrive = LeadStatus::where('name', 'like', '%Test Drive%')->first();
        $won = LeadStatus::where('name', 'like', '%Won%')->first();

        $leads = [
            [
                'name' => 'Captain Vikram Rathore',
                'email' => 'vikram.rathore@defmail.com',
                'phone' => '+91 98765 43210',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'vehicle_segment' => '4 Wheeler',
                'brand_id' => $tata ? $tata->id : null,
                'brand_name' => 'Tata Motors',
                'model_variant' => 'Safari Adventure Plus Dark Edition',
                'priority' => 'Hot',
                'purchase_timeline' => 'Immediate (Within 7 Days)',
                'source_id' => $website ? $website->id : null,
                'source_name' => 'Website',
                'status_id' => $newStatus ? $newStatus->id : null,
                'status_name' => 'New',
                'assigned_user_name' => 'David Miller (Sales Executive)',
            ],
            [
                'name' => 'Major Ankit Sharma',
                'email' => 'ankit.sharma@army.in',
                'phone' => '+91 98112 34567',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'vehicle_segment' => '4 Wheeler',
                'brand_id' => $maruti ? $maruti->id : null,
                'brand_name' => 'Maruti Suzuki',
                'model_variant' => 'Grand Vitara Alpha+ Hybrid',
                'priority' => 'Hot',
                'purchase_timeline' => '15-30 Days',
                'source_id' => $walkin ? $walkin->id : null,
                'source_name' => 'Showroom Walk-in',
                'status_id' => $testdrive ? $testdrive->id : null,
                'status_name' => 'Test Drive Scheduled',
                'assigned_user_name' => 'Alexander Vance (Sales Director)',
            ],
            [
                'name' => 'Dr. Meera Nambiar',
                'email' => 'meera.nambiar@medcare.org',
                'phone' => '+91 98450 99881',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'vehicle_segment' => '4 Wheeler',
                'brand_id' => $mahindra ? $mahindra->id : null,
                'brand_name' => 'Mahindra & Mahindra',
                'model_variant' => 'Scorpio-N Z8 L 4x4',
                'priority' => 'Warm',
                'purchase_timeline' => '1-3 Months',
                'source_id' => $facebook ? $facebook->id : null,
                'source_name' => 'Facebook',
                'status_id' => $followup ? $followup->id : null,
                'status_name' => 'In Follow-Up',
                'assigned_user_name' => 'Rajesh Kumar (Sales Executive)',
            ],
            [
                'name' => 'Lt. Col. Rajesh Deshmukh',
                'email' => 'rajesh.deshmukh@defence.gov.in',
                'phone' => '+91 98230 45678',
                'city' => 'Jaipur',
                'state' => 'Rajasthan',
                'vehicle_segment' => '2 Wheeler',
                'brand_id' => $enfield ? $enfield->id : null,
                'brand_name' => 'Royal Enfield',
                'model_variant' => 'Classic 350 Stealth Black',
                'priority' => 'Hot',
                'purchase_timeline' => 'Immediate (Within 7 Days)',
                'source_id' => $walkin ? $walkin->id : null,
                'source_name' => 'Showroom Walk-in',
                'status_id' => $won ? $won->id : null,
                'status_name' => 'Deal Won',
                'assigned_user_name' => 'David Miller (Sales Executive)',
            ],
        ];

        foreach ($leads as $lead) {
            Lead::firstOrCreate(['phone' => $lead['phone']], $lead);
        }
    }
}
