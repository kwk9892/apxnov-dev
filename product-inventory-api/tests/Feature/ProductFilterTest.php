<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    public function test_it_filters_products_by_category(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        Product::factory()->count(3)->create(['category_id' => $categoryA->id]);
        Product::factory()->count(2)->create(['category_id' => $categoryB->id]);

        $response = $this->getJson("/api/products?category_id={$categoryA->id}");

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_it_filters_products_by_price_range(): void
    {
        Product::factory()->create(['price' => 5]);
        Product::factory()->create(['price' => 50]);
        Product::factory()->create(['price' => 500]);

        $response = $this->getJson('/api/products?min_price=10&max_price=100');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price', 50);
    }

    public function test_it_filters_products_by_stock_level(): void
    {
        Product::factory()->create(['stock' => 0]);
        Product::factory()->create(['stock' => 5]);
        Product::factory()->create(['stock' => 100]);

        $response = $this->getJson('/api/products?stock_level=out_of_stock');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock_status', 'out_of_stock');
    }
}
