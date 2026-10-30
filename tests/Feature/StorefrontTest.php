<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\DeliveryZone;
use App\Models\PageBlock;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_homepage_renders_the_imported_banner_and_featured_product(): void
    {
        $this->seedCatalog();

        $this->get('/')
            ->assertOk()
            ->assertSee('Kitchen appliance')
            ->assertSee('/uploads/test-banner.webp')
            ->assertSee('Demo Product');
    }

    public function test_shop_search_filters_the_catalog(): void
    {
        $this->seedCatalog();

        $this->get('/shop?search=Demo')->assertOk()->assertSee('Demo Product');
        $this->get('/shop?search=missing-item')->assertOk()->assertDontSee('Demo Product');
    }

    public function test_cart_adds_a_product_using_the_session(): void
    {
        $this->seedCatalog();

        $this->postJson('/cart/items/demo-product', ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('cartCount', 2);

        $this->assertSame(2, (int) session('cart')['demo-product-id']);
    }

    public function test_checkout_creates_a_cod_order_and_updates_stock(): void
    {
        $this->seedCatalog();
        DeliveryZone::create(['id' => 'dhaka-test', 'name' => 'Inside Dhaka', 'charge' => 800, 'is_active' => true, 'sort_order' => 1]);

        $response = $this->withSession(['cart' => ['demo-product-id' => 1]])->post('/checkout', [
            'customer_name' => 'Test Buyer',
            'phone' => '01700000000',
            'email' => 'buyer@example.com',
            'address' => '12 Demo Road',
            'district' => 'Dhaka',
            'delivery_zone_id' => 'dhaka-test',
        ]);

        $order = \App\Models\Order::query()->firstOrFail();
        $response->assertRedirect(route('order.success', $order->order_number));
        $this->assertDatabaseHas('orders', ['order_number' => $order->order_number, 'payment_method' => 'COD', 'total' => 3300]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => 'demo-product-id', 'quantity' => 1]);
        $this->assertDatabaseHas('products', ['id' => 'demo-product-id', 'stock' => 3, 'sold_count' => 1]);
    }

    private function seedCatalog(): void
    {
        Category::create(['id' => 'demo-category-id', 'name' => 'Kitchen appliance', 'slug' => 'kitchen-appliance']);
        Product::create([
            'id' => 'demo-product-id', 'name' => 'Demo Product', 'slug' => 'demo-product', 'brand' => 'Best Cool',
            'regular_price' => 2500, 'stock' => 4, 'is_active' => true, 'sold_count' => 0, 'category_id' => 'demo-category-id',
        ]);
        ContentPage::create(['id' => 'home-test-id', 'slug' => 'home', 'title' => 'Home']);
        PageBlock::create([
            'id' => 'hero-test-id', 'page_id' => 'home-test-id', 'type' => 'BANNER',
            'content' => ['image' => '/uploads/test-banner.webp', 'link' => '/shop'], 'sort_order' => 0, 'is_enabled' => true,
        ]);
        PageBlock::create([
            'id' => 'featured-test-id', 'page_id' => 'home-test-id', 'type' => 'FEATURED_PRODUCTS',
            'content' => ['heading' => 'Featured Products', 'productIds' => ['demo-product-id']], 'sort_order' => 1, 'is_enabled' => true,
        ]);
    }
}
