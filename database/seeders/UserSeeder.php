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
    // Ne seed que si l'admin n'existe pas déjà
    if (!User::where('email', 'admin@example.com')->exists()) {
        $proPlan = Plan::where('slug', 'pro')->first();

        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => bcrypt('admin123!'),
            'plan_id' => $proPlan->id,
        ]);
    }

    // Ne crée les 500 users que si il y en a moins de 10 (évite d'en créer à chaque redémarrage)
    if (User::count() < 10) {
        User::factory(100)->create();
    }
}
}
