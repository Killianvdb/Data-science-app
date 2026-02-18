<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'price' => 0,
                'monthly_limit' => 10,
                'max_file_size_mb' => 2,
                'max_files_per_transaction' => 2,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'medium'],
            [
                'name' => 'Medium',
                'price' => 1000, // 10€
                'monthly_limit' => 50,
                'max_file_size_mb' => 10,
                'max_files_per_transaction' => 5,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'price' => 2500, // 25€
                'monthly_limit' => 1000, // even we call it unlimited we set a high limit to prevent abuse
                'max_file_size_mb' => 20,
                'max_files_per_transaction' => 10,
            ]
        );



        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => bcrypt('admin123!'),
            'plan_id' => Plan::where('slug', 'pro')->first()->id,
        ]);


        User::factory(500)->create();



    }
}
