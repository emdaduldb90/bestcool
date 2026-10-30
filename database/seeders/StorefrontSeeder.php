<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\DeliveryZone;
use App\Models\PageBlock;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use JsonException;

class StorefrontSeeder extends Seeder
{
    /** @throws JsonException */
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/storefront.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data['categories'] as $row) {
            Category::query()->updateOrCreate(['id' => $row['id']], [
                'name' => $row['name'], 'slug' => $row['slug'], 'image' => $row['image'],
                'created_at' => $row['createdAt'], 'updated_at' => $row['updatedAt'],
            ]);
        }

        foreach ($data['products'] as $row) {
            Product::query()->updateOrCreate(['id' => $row['id']], [
                'name' => $row['name'], 'slug' => $row['slug'], 'brand' => $row['brand'],
                'description' => $row['description'] ?? '', 'specifications' => $row['specifications'] ?? '',
                'questions' => $row['questions'], 'sku' => $row['sku'],
                'regular_price' => $row['regularPrice'], 'sale_price' => $row['salePrice'],
                'stock' => $row['stock'], 'low_stock_threshold' => $row['lowStockThreshold'],
                'is_active' => (bool) $row['isActive'], 'sold_count' => $row['soldCount'],
                'category_id' => $row['categoryId'], 'created_at' => $row['createdAt'], 'updated_at' => $row['updatedAt'],
            ]);
        }

        foreach ($data['images'] as $row) {
            ProductImage::query()->updateOrCreate(['id' => $row['id']], [
                'product_id' => $row['productId'], 'url' => $row['url'], 'alt' => $row['alt'], 'sort_order' => $row['sortOrder'],
            ]);
        }

        foreach ($data['pages'] as $row) {
            ContentPage::query()->updateOrCreate(['id' => $row['id']], [
                'slug' => $row['slug'], 'title' => $row['title'], 'meta_title' => $row['metaTitle'],
                'meta_description' => $row['metaDescription'], 'og_image' => $row['ogImage'],
                'created_at' => $row['createdAt'], 'updated_at' => $row['updatedAt'],
            ]);
        }

        foreach ($data['blocks'] as $row) {
            PageBlock::query()->updateOrCreate(['id' => $row['id']], [
                'page_id' => $row['pageId'], 'type' => $row['type'], 'content' => $row['content'],
                'sort_order' => $row['sortOrder'], 'is_enabled' => (bool) $row['isEnabled'],
            ]);
        }

        foreach ($data['settings'] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }

        foreach ($data['deliveryZones'] as $row) {
            DeliveryZone::query()->updateOrCreate(['id' => $row['id']], [
                'name' => $row['name'], 'charge' => $row['charge'],
                'is_active' => (bool) $row['isActive'], 'sort_order' => $row['sortOrder'],
            ]);
        }
    }
}
