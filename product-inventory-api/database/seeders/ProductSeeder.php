<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoryIds = Category::query()->pluck('id');
        $supplierIds = Supplier::query()->pluck('id');

        Product::factory()
            ->count(30)
            ->create()
            ->each(function (Product $product) use ($categoryIds, $supplierIds) {
                $product->update(['category_id' => $categoryIds->random()]);
                $product->suppliers()->attach(
                    $supplierIds->random(random_int(1, 3))->all()
                );
            });
    }
}
