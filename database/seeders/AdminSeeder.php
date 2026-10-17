<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds 5 predefined administrator accounts directly without a factory.
     */
    public function run(): void
    {
        $admins = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Operations Administrator',
                'email' => 'admin2@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Support Lead Administrator',
                'email' => 'admin3@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Security Administrator',
                'email' => 'admin4@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Audit Administrator',
                'email' => 'admin5@example.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($admins as $adminData) {
            Admin::firstOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );
        }
    }
}
