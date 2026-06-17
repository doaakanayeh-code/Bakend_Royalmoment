<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \Database\Factories\AdminFactory::new()->withToken()->create();
        // User::factory(10)->create();

       User::factory()->create([
    'username' => 'Test User', // التعديل هنا
    'email' => 'test@example.com',
]);
    }
}
