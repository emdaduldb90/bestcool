<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\PageBlock;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_block_order_is_reflected_on_the_public_page(): void
    {
        $page = ContentPage::query()->create(['id' => (string) Str::uuid(), 'slug' => 'about-us', 'title' => 'About us']);
        PageBlock::query()->create(['id' => (string) Str::uuid(), 'page_id' => $page->id, 'type' => 'RICH_TEXT', 'content' => ['html' => '<p>First managed section</p>'], 'sort_order' => 1, 'is_enabled' => true]);
        PageBlock::query()->create(['id' => (string) Str::uuid(), 'page_id' => $page->id, 'type' => 'RICH_TEXT', 'content' => ['html' => '<p>Second managed section</p>'], 'sort_order' => 2, 'is_enabled' => true]);

        $response = $this->get(route('page.show', 'about-us'))->assertOk()->assertSee('First managed section')->assertSee('Second managed section');
        $this->assertLessThan(strpos($response->getContent(), 'Second managed section'), strpos($response->getContent(), 'First managed section'));
    }

    public function test_admin_can_add_a_typed_cms_section_that_appears_on_the_public_page(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();
        $page = ContentPage::query()->create(['id' => (string) Str::uuid(), 'slug' => 'about-us', 'title' => 'About us']);

        $this->actingAs($admin)->post(route('admin.pages.blocks.store', $page), [
            'type' => 'RICH_TEXT',
            'content' => ['heading' => 'Our service promise', 'subheading' => '', 'html' => '<p>Managed storefront content</p>'],
        ])->assertRedirect();

        $this->get(route('page.show', 'about-us'))->assertOk()->assertSee('Our service promise')->assertSee('Managed storefront content');
    }

    public function test_product_faqs_render_with_safe_html(): void
    {
        $category = Category::query()->create(['id' => (string) Str::uuid(), 'name' => 'FAQ category', 'slug' => 'faq-category']);
        $product = Product::query()->create([
            'id' => (string) Str::uuid(), 'name' => 'FAQ product', 'slug' => 'faq-product', 'regular_price' => 120,
            'stock' => 2, 'is_active' => true, 'category_id' => $category->id,
            'questions' => [['question' => 'How long is the warranty?', 'answer' => '<p>One year <script>alert(1)</script><strong>included</strong>.</p>']],
        ]);

        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('How long is the warranty?')->assertSee('One year')->assertSee('included')->assertDontSee('<script>', false);
    }
}
