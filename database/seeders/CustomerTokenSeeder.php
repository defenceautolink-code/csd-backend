<?php

namespace Database\Seeders;

use App\Models\CustomerToken;
use App\Models\InstallmentInvoice;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerTokenSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        // 1. Subedar Rajesh Sharma (TKN-2026-1042)
        $t1 = CustomerToken::create([
            'token_number' => 'TKN-2026-1042',
            'token_date' => '2026-09-10',
            'customer_name' => 'Subedar Rajesh Sharma',
            'customer_phone' => '98765 43210',
            'customer_email' => 'rajesh.sharma@army.in',
            'service_id' => 'JC-458921X',
            'rank_designation' => 'Subedar',
            'brand_name' => 'Maruti Suzuki',
            'vehicle_name' => 'Maruti Suzuki Grand Vitara',
            'model_variant' => 'Zeta 1.5L Smart Hybrid Petrol',
            'total_deal_amount' => 1450000.00,
            'total_paid_amount' => 550000.00,
            'remaining_balance' => 900000.00,
            'paid_percentage' => 38.0,
            'invoices_count' => 3,
            'status' => 'Partial',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0001',
            'invoice_date' => '2026-09-10',
            'token_id' => $t1->id,
            'customer_name' => $t1->customer_name,
            'customer_phone' => $t1->customer_phone,
            'service_id' => $t1->service_id,
            'booked_vehicle' => 'Maruti Suzuki Grand Vitara (Zeta 1.5L)',
            'bill_type' => 'Token Advance',
            'invoice_amount' => 42372.88,
            'tax_amount' => 7627.12,
            'net_payable' => 50000.00,
            'payment_mode' => 'UPI',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0002',
            'invoice_date' => '2026-09-15',
            'token_id' => $t1->id,
            'customer_name' => $t1->customer_name,
            'customer_phone' => $t1->customer_phone,
            'service_id' => $t1->service_id,
            'booked_vehicle' => 'Maruti Suzuki Grand Vitara (Zeta 1.5L)',
            'bill_type' => 'Part Payment 1',
            'invoice_amount' => 211864.41,
            'tax_amount' => 38135.59,
            'net_payable' => 250000.00,
            'payment_mode' => 'NEFT/RTGS',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0003',
            'invoice_date' => '2026-09-20',
            'token_id' => $t1->id,
            'customer_name' => $t1->customer_name,
            'customer_phone' => $t1->customer_phone,
            'service_id' => $t1->service_id,
            'booked_vehicle' => 'Maruti Suzuki Grand Vitara (Zeta 1.5L)',
            'bill_type' => 'Part Payment 2',
            'invoice_amount' => 211864.41,
            'tax_amount' => 38135.59,
            'net_payable' => 250000.00,
            'payment_mode' => 'Bank Transfer',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        // 2. Major Vikramaditya Singh (TKN-2026-1088)
        $t2 = CustomerToken::create([
            'token_number' => 'TKN-2026-1088',
            'token_date' => '2026-09-12',
            'customer_name' => 'Major Vikramaditya Singh',
            'customer_phone' => '98251 23456',
            'customer_email' => 'vikramaditya.singh@army.in',
            'service_id' => 'IC-672109M',
            'rank_designation' => 'Major',
            'brand_name' => 'Hyundai',
            'vehicle_name' => 'Hyundai New Venue',
            'model_variant' => '1.0 Turbo DCT HX5 Petrol',
            'total_deal_amount' => 1095000.00,
            'total_paid_amount' => 825000.00,
            'remaining_balance' => 270000.00,
            'paid_percentage' => 75.0,
            'invoices_count' => 2,
            'status' => 'Partial',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0004',
            'invoice_date' => '2026-09-12',
            'token_id' => $t2->id,
            'customer_name' => $t2->customer_name,
            'customer_phone' => $t2->customer_phone,
            'service_id' => $t2->service_id,
            'booked_vehicle' => 'Hyundai New Venue (1.0 Turbo DCT)',
            'bill_type' => 'Token Advance',
            'invoice_amount' => 63559.32,
            'tax_amount' => 11440.68,
            'net_payable' => 75000.00,
            'payment_mode' => 'UPI',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0005',
            'invoice_date' => '2026-09-18',
            'token_id' => $t2->id,
            'customer_name' => $t2->customer_name,
            'customer_phone' => $t2->customer_phone,
            'service_id' => $t2->service_id,
            'booked_vehicle' => 'Hyundai New Venue (1.0 Turbo DCT)',
            'bill_type' => 'Part Payment 1',
            'invoice_amount' => 635593.22,
            'tax_amount' => 114406.78,
            'net_payable' => 750000.00,
            'payment_mode' => 'NEFT/RTGS',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        // 3. Havildar Amit Deshmukh (TKN-2026-1120)
        $t3 = CustomerToken::create([
            'token_number' => 'TKN-2026-1120',
            'token_date' => '2026-09-22',
            'customer_name' => 'Havildar Amit Deshmukh',
            'customer_phone' => '94210 98765',
            'customer_email' => 'amit.deshmukh@army.in',
            'service_id' => '15482910K',
            'rank_designation' => 'Havildar',
            'brand_name' => 'Tata Motors',
            'vehicle_name' => 'Tata Nexon',
            'model_variant' => 'Fearless Plus S 1.2 Turbo Petrol',
            'total_deal_amount' => 1320000.00,
            'total_paid_amount' => 50000.00,
            'remaining_balance' => 1270000.00,
            'paid_percentage' => 4.0,
            'invoices_count' => 1,
            'status' => 'Partial',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0006',
            'invoice_date' => '2026-09-22',
            'token_id' => $t3->id,
            'customer_name' => $t3->customer_name,
            'customer_phone' => $t3->customer_phone,
            'service_id' => $t3->service_id,
            'booked_vehicle' => 'Tata Nexon (Fearless Plus S)',
            'bill_type' => 'Token Advance',
            'invoice_amount' => 42372.88,
            'tax_amount' => 7627.12,
            'net_payable' => 50000.00,
            'payment_mode' => 'UPI',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        // 4. Captain Sunita Rawat (TKN-2026-0955)
        $t4 = CustomerToken::create([
            'token_number' => 'TKN-2026-0955',
            'token_date' => '2026-08-28',
            'customer_name' => 'Captain Sunita Rawat',
            'customer_phone' => '98980 11223',
            'customer_email' => 'sunita.rawat@army.in',
            'service_id' => 'MS-18492P',
            'rank_designation' => 'Captain',
            'brand_name' => 'Mahindra',
            'vehicle_name' => 'Mahindra XUV700',
            'model_variant' => 'AX7 Diesel AT Luxury Pack',
            'total_deal_amount' => 2180000.00,
            'total_paid_amount' => 2180000.00,
            'remaining_balance' => 0.00,
            'paid_percentage' => 100.0,
            'invoices_count' => 3,
            'status' => 'Settled',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/08/0007',
            'invoice_date' => '2026-08-28',
            'token_id' => $t4->id,
            'customer_name' => $t4->customer_name,
            'customer_phone' => $t4->customer_phone,
            'service_id' => $t4->service_id,
            'booked_vehicle' => 'Mahindra XUV700 (AX7 Diesel AT)',
            'bill_type' => 'Token Advance',
            'invoice_amount' => 84745.76,
            'tax_amount' => 15254.24,
            'net_payable' => 100000.00,
            'payment_mode' => 'Bank Transfer',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0008',
            'invoice_date' => '2026-09-05',
            'token_id' => $t4->id,
            'customer_name' => $t4->customer_name,
            'customer_phone' => $t4->customer_phone,
            'service_id' => $t4->service_id,
            'booked_vehicle' => 'Mahindra XUV700 (AX7 Diesel AT)',
            'bill_type' => 'Part Payment 1',
            'invoice_amount' => 847457.63,
            'tax_amount' => 152542.37,
            'net_payable' => 1000000.00,
            'payment_mode' => 'NEFT/RTGS',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);

        InstallmentInvoice::create([
            'invoice_number' => 'DAL-2026/09/0009',
            'invoice_date' => '2026-09-12',
            'token_id' => $t4->id,
            'customer_name' => $t4->customer_name,
            'customer_phone' => $t4->customer_phone,
            'service_id' => $t4->service_id,
            'booked_vehicle' => 'Mahindra XUV700 (AX7 Diesel AT)',
            'bill_type' => 'Final Settlement',
            'invoice_amount' => 915254.24,
            'tax_amount' => 164745.76,
            'net_payable' => 1080000.00,
            'payment_mode' => 'NEFT/RTGS',
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_by' => $user?->id,
        ]);
    }
}
