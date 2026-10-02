<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use Illuminate\Database\Seeder;

class LeadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['title' => 'Showroom Walk-in', 'status' => 'Active'],
            ['title' => 'Website', 'status' => 'Active'],
            ['title' => 'Facebook', 'status' => 'Active'],
            ['title' => 'Instagram', 'status' => 'Active'],
            ['title' => 'Reference', 'status' => 'Active'],
            ['title' => 'Google Search Ads', 'status' => 'Active'],
            ['title' => 'CarDekho / CarWale', 'status' => 'Active'],
            ['title' => 'Other', 'status' => 'Active'],
        ];

        foreach ($sources as $source) {
            LeadSource::firstOrCreate(['title' => $source['title']], $source);
        }
    }
}
