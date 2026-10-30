<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\ContentPage;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\User;
use Database\Seeders\AdminAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AdminAccessSeeder::class)->run();
    }

    public function test_staff_permissions_are_enforced_on_admin_routes(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_active' => true])->save();
        $support = Role::query()->where('slug', 'support')->firstOrFail();
        $staff->roles()->attach($support->id, ['assigned_at' => now()]);

        $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.customers.index'))->assertOk();
        $this->get(route('admin.products.index'))->assertForbidden();
        $this->get(route('admin.staff.index'))->assertForbidden();
    }

    public function test_admin_module_pages_render_with_empty_data(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();
        $home = ContentPage::query()->create(['id' => (string) Str::uuid(), 'slug' => 'home', 'title' => 'Home']);
        $this->actingAs($admin);

        foreach ([
            'admin.dashboard', 'admin.products.index', 'admin.brands.index', 'admin.inventory.index',
            'admin.categories.index', 'admin.orders.index', 'admin.delivery-zones.index', 'admin.banners.index',
            'admin.pages.index', 'admin.pages.edit', 'admin.customers.index', 'admin.inquiries.index',
            'admin.reports.index', 'admin.activity.index', 'admin.staff.index', 'admin.system.index', 'admin.settings.edit',
        ] as $name) {
            $parameters = $name === 'admin.pages.edit' ? ['page' => $home->id] : [];
            $this->get(route($name, $parameters))->assertOk();
        }
    }

    public function test_brand_screen_groups_and_searches_existing_product_brand_values(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();
        $category = Category::query()->create(['id' => (string) Str::uuid(), 'name' => 'Brand category', 'slug' => 'brand-category']);
        foreach (['brand-product-one', 'brand-product-two'] as $slug) {
            Product::query()->create([
                'id' => (string) Str::uuid(), 'name' => $slug, 'slug' => $slug, 'brand' => 'Arctic Systems',
                'regular_price' => 100, 'stock' => 2, 'is_active' => true, 'category_id' => $category->id,
            ]);
        }

        $this->actingAs($admin)->get(route('admin.brands.index', ['search' => 'Arctic']))
            ->assertOk()->assertSee('Arctic Systems')->assertSee('2');
    }

    public function test_inventory_adjustments_are_audited_and_cannot_go_negative(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();
        $category = Category::query()->create(['id' => (string) Str::uuid(), 'name' => 'Inventory category', 'slug' => 'inventory-category']);
        $product = Product::query()->create([
            'id' => (string) Str::uuid(), 'name' => 'Stock test product', 'slug' => 'stock-test-product',
            'regular_price' => 100, 'stock' => 3, 'low_stock_threshold' => 2, 'is_active' => true, 'category_id' => $category->id,
        ]);

        $this->actingAs($admin)->post(route('admin.inventory.adjust', $product), [
            'change_quantity' => 5, 'reason' => 'RESTOCK', 'notes' => 'New shipment',
        ])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 8]);
        $this->assertDatabaseHas('stock_adjustments', ['product_id' => $product->id, 'change_quantity' => 5, 'resulting_stock' => 8, 'actor_id' => $admin->id]);

        $this->post(route('admin.inventory.adjust', $product), [
            'change_quantity' => -9, 'reason' => 'CORRECTION',
        ])->assertSessionHasErrors('change_quantity');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 8]);
        $this->assertSame(1, StockAdjustment::query()->where('product_id', $product->id)->count());
    }

    public function test_contact_messages_are_stored_and_opening_them_marks_them_read(): void
    {
        $this->post(route('inquiries.store'), [
            'name' => 'A customer', 'email' => 'customer@example.com', 'phone' => '01700000000',
            'subject' => 'Delivery question', 'message' => 'Can you deliver this item to my area?',
        ])->assertRedirect()->assertSessionHas('success');

        $inquiry = Inquiry::query()->firstOrFail();
        $this->assertSame('NEW', $inquiry->status);
        $this->assertNull($inquiry->user_id);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();
        $this->actingAs($admin)->get(route('admin.inquiries.show', $inquiry))->assertOk()->assertSee('Delivery question');
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'status' => 'READ']);
    }

    public function test_dashboard_metrics_use_the_selected_period_and_exclude_cancelled_order_value(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();
        foreach ([
            ['BCE-METRIC-001', 'PENDING', 100, '2026-10-01 10:00:00'],
            ['BCE-METRIC-002', 'DELIVERED', 200, '2026-10-02 10:00:00'],
            ['BCE-METRIC-003', 'CANCELLED', 500, '2026-10-03 10:00:00'],
            ['BCE-METRIC-004', 'PENDING', 900, '2026-09-30 10:00:00'],
        ] as [$number, $status, $total, $createdAt]) {
            $order = Order::query()->create([
                'order_number' => $number, 'customer_name' => 'Metrics customer', 'phone' => '01700000000',
                'address' => 'Dhaka', 'subtotal' => $total, 'delivery_fee' => 0, 'total' => $total,
                'status' => $status, 'payment_method' => 'COD',
            ]);
            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        $this->actingAs($admin)->get(route('admin.dashboard', [
            'period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-03',
        ]))->assertOk()->assertViewHas('stats', fn (array $stats) =>
            $stats['orders'] === 3 && $stats['pending'] === 1 && $stats['delivered'] === 1
            && $stats['cancelled'] === 1 && $stats['orderValue'] === 300.0
            && $stats['deliveredValue'] === 200.0 && $stats['averageOrderValue'] === 150.0
        );
    }
}
