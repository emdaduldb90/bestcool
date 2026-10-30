<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class CmsController extends Controller
{
    public function show(string $slug): View
    {
        $page = ContentPage::query()
            ->with(['blocks' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
            ->where('slug', $slug)
            ->firstOrFail();

        $displayBlocks = $page->blocks->map(function ($block) {
            $content = $block->content ?? [];
            $result = ['block' => $block, 'categories' => collect(), 'products' => collect()];
            if ($block->type === 'FEATURED_CATEGORIES') {
                $ids = (array) ($content['categoryIds'] ?? []);
                $result['categories'] = Category::query()->where('is_active', true)->withCount(['products' => fn ($query) => $query->where('is_active', true)])
                    ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))->orderBy('sort_order')->orderBy('name')->limit(12)->get();
            }
            if (in_array($block->type, ['FEATURED_PRODUCTS', 'CATEGORY_PRODUCTS'], true)) {
                $ids = (array) ($content['productIds'] ?? []);
                $categoryId = $content['categoryId'] ?? null;
                $query = Product::query()->active()->with(['images', 'category'])->whereHas('category', fn ($q) => $q->where('is_active', true));
                if ($ids !== []) {
                    $positions = array_flip($ids);
                    $result['products'] = $query->whereIn('id', $ids)->get()->sortBy(fn ($product) => $positions[$product->id] ?? PHP_INT_MAX)->values();
                } elseif ($categoryId) {
                    $result['products'] = $query->where('category_id', $categoryId)->latest()->limit(10)->get();
                }
            }
            return $result;
        });

        return view('store.page', compact('page', 'displayBlocks'));
    }
}
