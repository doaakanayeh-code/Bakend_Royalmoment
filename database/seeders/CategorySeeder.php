<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        $categories = [
            ['name' => 'مصور', 'slug' => 'photographer'],
            ['name' => 'كيك', 'slug' => 'cake'],
            ['name' => 'تنسيق زهور', 'slug' => 'flowers'],
            ['name' => 'قاعات أفراح', 'slug' => 'halls'],
            ['name' => 'فستان عروس', 'slug' => 'dresses'],
            ['name' => 'صالون تجميل', 'slug' => 'salon'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
