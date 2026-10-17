<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agents = [
            [
                'name' => 'Agent One',
                'email' => 'agent1@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801712000001',
                'is_available' => true,
            ],
            [
                'name' => 'Agent Two',
                'email' => 'agent2@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801812000002',
                'is_available' => true,
            ],
            [
                'name' => 'Agent Three',
                'email' => 'agent3@example.com',
                'password' => Hash::make('password'),
                'phone' => '+8801912000003',
                'is_available' => true,
            ],
        ];

        foreach ($agents as $agentData) {
            Agent::firstOrCreate(
                ['email' => $agentData['email']],
                $agentData
            );
        }
    }
}
