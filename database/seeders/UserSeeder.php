<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'phone' => '+91 98000 11111',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPER_ADMIN->value,
                'status' => 'Active',
            ],
            [
                'name' => 'Alexander Vance',
                'email' => 'alexander.vance@carcrm.com',
                'phone' => '+91 98765 11223',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPER_ADMIN->value,
                'status' => 'Active',
            ],
            [
                'name' => 'Neha Sharma',
                'email' => 'neha.sharma@carcrm.com',
                'phone' => '+91 98765 22334',
                'password' => Hash::make('password'),
                'role' => UserRole::SALES_MANAGER->value,
                'status' => 'Active',
            ],
            [
                'name' => 'Rahul Verma',
                'email' => 'rahul.verma@carcrm.com',
                'phone' => '+91 98765 43210',
                'password' => Hash::make('password'),
                'role' => UserRole::SALES_EXECUTIVE->value,
                'status' => 'Active',
            ],
            [
                'name' => 'David Miller',
                'email' => 'david.miller@carcrm.com',
                'phone' => '+91 98765 55667',
                'password' => Hash::make('password'),
                'role' => UserRole::SALES_EXECUTIVE->value,
                'status' => 'Active',
            ],
            [
                'name' => 'Pooja Iyer',
                'email' => 'pooja.iyer@carcrm.com',
                'phone' => '+91 98765 66778',
                'password' => Hash::make('password'),
                'role' => UserRole::RECEPTIONIST->value,
                'status' => 'Active',
            ],
            [
                'name' => 'Amit Patel',
                'email' => 'amit.patel@carcrm.com',
                'phone' => '+91 98765 77889',
                'password' => Hash::make('password'),
                'role' => UserRole::ACCOUNTANT->value,
                'status' => 'Active',
            ],
            [
                'name' => 'Captain Vikram Rathore',
                'email' => 'vikram.rathore@defmail.com',
                'phone' => '+91 98765 99000',
                'password' => Hash::make('password'),
                'role' => UserRole::CUSTOMER->value,
                'status' => 'Active',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], $user);
        }
    }
}
