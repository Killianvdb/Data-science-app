<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Dataset;
use App\Models\User;

class DatasetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        $users = User::all();

        foreach ($users as $user) {

            $numberOfDatasets = rand(1, 3);

            for ($i = 0; $i < $numberOfDatasets; $i++) {

                Dataset::create([
                    'user_id' => $user->id,
                    'name' => fake()->words(3, true),
                    'original_name' => fake()->word() . '.csv',
                    'path' => 'datasets/' . fake()->uuid() . '.csv',
                    'status' => fake()->randomElement(['uploaded', 'processed', 'finalized']),
                    'rows' => rand(10, 500),
                    'columns' => rand(3, 15),
                ]);
            }
        }

    }
}
