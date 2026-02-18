<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'price' => 0,
                'monthly_limit' => 10,
                'max_total_mb_per_transaction' => 2,
                'max_files_per_transaction' => 2,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'medium'],
            [
                'name' => 'Medium',
                'price' => 1000,
                'monthly_limit' => 50,
                'max_total_mb_per_transaction' => 10,
                'max_files_per_transaction' => 5,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'price' => 2500,
                'monthly_limit' => 1000,
                'max_total_mb_per_transaction' => 20,
                'max_files_per_transaction' => 10,
            ]
        );
    }
}
