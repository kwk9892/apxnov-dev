<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_it_lists_products_with_pagination(): void
    {
        $this->authenticatedUser();
        Product::factory()->count(20)->create();

        $response = $this->getJson('/api/products?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_creates_a_product_with_valid_data(): void
    {
        $this->authenticatedUser();
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'sku' => 'wm-100',
            'price' => 29.99,
            'stock' => 50,
            'supplier_ids' => [$supplier->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'WM-100')
            ->assertJsonPath('data.stock_status', 'in_stock')
            ->assertJsonCount(1, 'data.suppliers');

        $this->assertDatabaseHas('products', ['sku' => 'WM-100']);
    }

    public function test_it_rejects_a_product_with_missing_required_fields(): void
    {
        $this->authenticatedUser();

        $response = $this->postJson('/api/products', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'name', 'sku', 'price', 'stock']);
    }

    public function test_it_updates_a_product(): void
    {
        $this->authenticatedUser();
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->putJson("/api/products/{$product->id}", ['stock' => 200]);

        $response->assertOk()->assertJsonPath('data.stock', 200);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 200]);
    }

    public function test_it_soft_deletes_a_product(): void
    {
        $this->authenticatedUser();
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_it_rejects_a_duplicate_sku(): void
    {
        $this->authenticatedUser();
        Product::factory()->create(['sku' => 'DUPLICATE-1']);
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Another Product',
            'sku' => 'duplicate-1',
            'price' => 10,
            'stock' => 1,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['sku']);
    }
}
