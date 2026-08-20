<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names = ['Electronics', 'Home & Kitchen', 'Sporting Goods', 'Office Supplies', 'Toys & Games'];

        foreach ($names as $name) {
            Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "{$name} products.",
            ]);
        }
    }
}
