<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PageBlock;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_routes_require_an_authenticated_administrator(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin')->assertForbidden();

        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertSee('Recent orders');
    }

    public function test_admin_can_create_a_product(): void
    {
        $admin = $this->admin();
        $category = $this->category();

        $response = $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Admin-created Product', 'slug' => '', 'sku' => 'ADM-001', 'brand' => 'Best Cool',
            'category_id' => $category->id, 'regular_price' => 2400, 'sale_price' => 2200,
            'stock' => 5, 'low_stock_threshold' => 2, 'description' => 'A product created in the admin panel.',
            'specifications' => 'Color: White', 'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.products.edit', 'admin-created-product'));
        $this->assertDatabaseHas('products', [
            'slug' => 'admin-created-product', 'category_id' => $category->id,
            'regular_price' => 2400, 'sale_price' => 2200, 'stock' => 5, 'is_active' => true,
        ]);
    }

    public function test_admin_can_create_a_category(): void
    {
        $this->actingAs($this->admin())->post(route('admin.categories.store'), [
            'name' => 'Admin Category', 'slug' => '', 'image' => '/uploads/category.webp',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['slug' => 'admin-category', 'image' => '/uploads/category.webp']);
    }

    public function test_category_image_upload_uses_a_unique_public_path(): void
    {
        $this->actingAs($this->admin())->post(route('admin.categories.store'), [
            'name' => 'Uploaded category', 'slug' => '', 'image_file' => UploadedFile::fake()->image('category.png'),
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'uploaded-category')->firstOrFail();
        $this->assertMatchesRegularExpression('#^/uploads/categories/[0-9a-f-]{36}\\.png$#i', $category->image);
        $path = public_path(ltrim($category->image, '/'));
        $this->assertFileExists($path);
        @unlink($path);
    }

    public function test_category_can_be_hidden_and_its_image_cleared(): void
    {
        $category = Category::query()->create([
            'id' => (string) Str::uuid(), 'name' => 'Old category', 'slug' => 'old-category',
            'image' => '/uploads/categories/old.webp', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->put(route('admin.categories.update', $category), [
            'name' => 'Hidden category', 'slug' => 'hidden-category', 'image' => '',
            'sort_order' => 0, 'is_active' => '0',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false, 'image' => null]);
    }

    public function test_admin_can_create_a_delivery_zone(): void
    {
        $this->actingAs($this->admin())->post(route('admin.delivery-zones.store'), [
            'name' => 'Outside Dhaka', 'charge' => 1200, 'sort_order' => 2, 'is_active' => '1',
        ])->assertRedirect(route('admin.delivery-zones.index'));

        $this->assertSame(1, DeliveryZone::query()->where('name', 'Outside Dhaka')->where('charge', 1200)->where('is_active', true)->count());
    }

    public function test_admin_can_update_store_contact_settings(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'settings' => [
                'site.name' => 'Updated Storefront Name', 'site.tagline' => 'Home essentials',
                'contact.phone' => '01700000000', 'contact.email' => 'info@example.com',
                'contact.address' => 'Dhaka', 'whatsapp.number' => '', 'whatsapp.message' => '',
                'seo.defaultTitle' => 'Best Cool', 'seo.defaultDescription' => 'Electronics store',
            ],
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('01700000000', Setting::query()->findOrFail('contact.phone')->value);
        $this->get(route('home'))->assertOk()->assertSee('Updated Storefront Name');
    }

    public function test_admin_can_add_a_homepage_banner(): void
    {
        $page = ContentPage::create(['id' => (string) Str::uuid(), 'slug' => 'home', 'title' => 'Home']);

        $this->actingAs($this->admin())->post(route('admin.banners.store'), [
            'image' => '/uploads/admin-banner.webp', 'alt' => 'Summer banner', 'link' => '/shop',
            'sort_order' => 0, 'is_enabled' => '1',
        ])->assertRedirect(route('admin.banners.index'));

        $block = PageBlock::query()->where('page_id', $page->id)->firstOrFail();
        $this->assertSame('BANNER', $block->type);
        $this->assertSame('/uploads/admin-banner.webp', $block->content['image']);
    }

    public function test_cancelling_an_order_restores_inventory_once(): void
    {
        $admin = $this->admin();
        $category = $this->category();
        $product = Product::create([
            'id' => (string) Str::uuid(), 'name' => 'Reserved product', 'slug' => 'reserved-product',
            'regular_price' => 100, 'stock' => 0, 'sold_count' => 2, 'is_active' => true,
            'category_id' => $category->id,
        ]);
        $order = Order::create([
            'order_number' => 'BCE-TEST-001', 'customer_name' => 'Test customer', 'phone' => '01700000000',
            'address' => 'Dhaka', 'subtotal' => 200, 'delivery_fee' => 0, 'total' => 200,
            'status' => 'PROCESSING', 'payment_method' => 'COD',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'unit_price' => 100, 'quantity' => 2, 'line_total' => 200,
        ]);

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'CANCELLED'])
            ->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2, 'sold_count' => 0]);

        $this->patch(route('admin.orders.status', $order), ['status' => 'PENDING'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2, 'sold_count' => 0]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function category(): Category
    {
        return Category::create(['id' => (string) Str::uuid(), 'name' => 'Admin test category', 'slug' => 'admin-test-category']);
    }
}
