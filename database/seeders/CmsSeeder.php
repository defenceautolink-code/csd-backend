<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Blog;
use App\Models\CrmPage;
use App\Models\ContactInquiry;
use Carbon\Carbon;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Initial Blogs
        if (Blog::count() === 0) {
            Blog::create([
                'title' => 'Best Blue Cars in India',
                'slug' => 'best-blue-cars-in-india',
                'publish_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
                'status' => 'active',
                'views' => 72,
                'likes' => 3,
                'shares' => 2,
                'img' => '/visitor/home/1775506047_Tata-Safari-Facelift-1160x653.webp',
                'details' => '<p>Choosing a car can be a daunting task in itself, with so many cars to choose from at so many different price points. But even if you have narrowed down your options, there is still an important factor that comes into play: the colour of your new ride.</p><p>With so many colours and shades to choose from, picking the perfect colour can feel overwhelming. While white may seem too common, red too bold, and black too prone to dust accumulation, the colour blue emerges as a favourite amongst many car buyers. Hence, if you are a fan of this timeless and versatile colour, there are many cars in India that look stunning in blue shades.</p><h3>Top Blue Cars Highlights:</h3><ul><li><strong>Maruti Suzuki Dzire</strong> - Oxford Blue (Rs 6.52 to 9.39 Lakh)</li><li><strong>Maruti Suzuki Fronx</strong> - NEXA Blue (Rs 7.47 to 13.14 Lakh)</li><li><strong>Tata Nexon</strong> - Royal Blue (Rs 7.80 to 8.00 Lakh)</li><li><strong>Tata Safari</strong> - Royale Blue (Rs 15.65 to 25.01 Lakh)</li></ul><p>First up on this list is the Maruti Dzire, which is sold in a handsome Oxford Blue colour, the best selling and flagship colour of the Dzire. The colour adds a premium character to the car!</p>',
            ]);

            Blog::create([
                'title' => 'VinFast VF MPV 7 to launch on 15 April',
                'slug' => 'vinfast-vf-mpv-7-to-launch-on-15-april',
                'publish_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'status' => 'active',
                'views' => 96,
                'likes' => 5,
                'shares' => 4,
                'img' => '/visitor/home/1775506132_VinFast-VF-MPV-7-1160x653.webp',
                'details' => '<p>In a bid to expand its line-up in India, VinFast will launch its 7-seater electric MPV, the VF MPV 7. Designed for comfort, cutting-edge technology, and eco-friendly performance, this vehicle is tailored for Indian families looking for space and sustainability.</p><p>VinFast upcoming entry is set to shake up the electric multi-purpose vehicle segment in India. With spacious three-row seating, advanced driver-assistance systems (ADAS), and a battery offering upwards of 450 km of range on a single charge, the VF MPV 7 represents a bold new chapter for clean mobility.</p>',
            ]);
        }

        // 2. Initial CRM Pages (Useful Links)
        if (CrmPage::count() === 0) {
            CrmPage::create([
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'position' => 1,
                'status' => 'active',
                'detail' => '<h2>Privacy Policy</h2><p>Welcome to Defence Autolink (CSD). We are dedicated to safeguarding your privacy and ensuring your personal information is protected with the highest security standards.</p><p>We collect essential information such as your contact details, rank, posting, and vehicle requirements solely for processing your CSD booking and delivery requirements. Your data is never sold or shared with unauthorized third parties.</p>',
            ]);

            CrmPage::create([
                'title' => 'Terms & Conditions',
                'slug' => 'terms-conditions',
                'position' => 2,
                'status' => 'active',
                'detail' => '<h2>Terms and Conditions</h2><p>These terms and conditions govern your use of the Defence Autolink portal and dealership inquiry services. By accessing our services, you agree to comply with all relevant CSD guidelines and dealership terms.</p><p>Vehicle allocations and price quotations are subject to manufacturer revisions, depot allocations, and eligibility validation.</p>',
            ]);

            CrmPage::create([
                'title' => 'Buyer Terms and Conditions',
                'slug' => 'buyer-terms-conditions',
                'position' => 3,
                'status' => 'active',
                'detail' => '<h2>Buyer Terms and Conditions</h2><p>All purchases processed through our CSD dealership assistance platform are subject to verification of valid defence identity credentials, entitlement letters, and requisite payment clearances through official channels.</p>',
            ]);

            CrmPage::create([
                'title' => 'Disclaimer',
                'slug' => 'disclaimer',
                'position' => 4,
                'status' => 'active',
                'detail' => '<h2>CSD Portal Disclaimer</h2><p>Prices, specifications, and availability displayed on this website are indicative and subject to change without prior notice. Final on-road prices depend on state taxes, registration fees, and insurance selection at the time of delivery.</p>',
            ]);
        }
    }
}
