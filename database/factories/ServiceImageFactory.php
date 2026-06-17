<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceImage>
 */
class ServiceImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
{
    return [
        // سيتم ربطها بالخدمة لاحقاً في الـ Seeder
        'service_id' => \App\Models\Service::factory(), 
        'image_path' => $this->faker->imageUrl(640, 480, 'business'), // رابط صورة عشوائية
    ];
}
}
