<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Technical Support', 'is_active' => true],
            ['name' => 'Billing & Payments', 'is_active' => true],
            ['name' => 'Account & Security', 'is_active' => true],
            ['name' => 'Feature Request', 'is_active' => true],
            ['name' => 'Bug Report', 'is_active' => true],
            ['name' => 'General Inquiry', 'is_active' => true],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }
    }
}
