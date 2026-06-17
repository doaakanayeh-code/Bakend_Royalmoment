<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            // جعلناهم يرجعون NULL هنا لأن الـ DatabaseSeeder يمررهم يدوياً وجاهزين
            'user_id' => null,
            'category_id' => null,
            
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'price_start' => $this->faker->randomFloat(2, 50, 1000),
            'city' => $this->faker->city(),
            'address' => $this->faker->address(),
            'capacity' => $this->faker->optional(0.3)->numberBetween(50, 500), 
            'status' => 'active',
        ];
    }
}