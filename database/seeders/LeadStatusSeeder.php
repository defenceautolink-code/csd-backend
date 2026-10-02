<?php

namespace Database\Seeders;

use App\Models\LeadStatus;
use Illuminate\Database\Seeder;

class LeadStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'New', 'status' => 'Active'],
            ['name' => 'In Follow-Up', 'status' => 'Active'],
            ['name' => 'Test Drive Scheduled', 'status' => 'Active'],
            ['name' => 'Quotation Sent', 'status' => 'Active'],
            ['name' => 'Negotiation', 'status' => 'Active'],
            ['name' => 'Deal Won', 'status' => 'Active'],
            ['name' => 'Deal Lost', 'status' => 'Active'],
        ];

        foreach ($statuses as $item) {
            LeadStatus::firstOrCreate(['name' => $item['name']], $item);
        }
    }
}
