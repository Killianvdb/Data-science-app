<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Plan;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        $proPlan = Plan::where('slug', 'pro')->first();

        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => bcrypt('admin123!'),
            'plan_id' => $proPlan->id,
        ]);

        
        User::factory(500)->create();

    }
}
