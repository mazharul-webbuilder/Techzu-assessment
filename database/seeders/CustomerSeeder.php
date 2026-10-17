<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'name' => 'Jawadul',
                'email' => 'customer1@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801711000001',
            ],
            [
                'name' => 'Monirujjaman',
                'email' => 'customer2@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801811000002',
            ],
            [
                'name' => 'Zayaan',
                'email' => 'customer3@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801911000003',
            ],
            [
                'name' => 'Nafia',
                'email' => 'customer4@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801611000004',
            ],
            [
                'name' => 'Esha',
                'email' => 'customer5@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801511000005',
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate(
                ['email' => $customerData['email']],
                $customerData
            );
        }
    }
}
