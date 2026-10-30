<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\PageBlock;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $homePage = ContentPage::query()->where('slug', 'home')
            ->with(['blocks' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')])
            ->first();
        $blocks = $homePage?->blocks ?? collect();

        $banners = $blocks->filter(fn (PageBlock $block) => $block->type === 'BANNER' && ! empty($block->content['image']))->values();
        $displayBlocks = $blocks->reject(fn (PageBlock $block) => $block->type === 'BANNER')->map(function (PageBlock $block) {
            $content = $block->content ?? [];
            $result = ['block' => $block, 'products' => collect(), 'categories' => collect()];

            if ($block->type === 'FEATURED_CATEGORIES') {
                $ids = array_values(array_filter((array) ($content['categoryIds'] ?? [])));
                $categories = Category::query()->where('is_active', true)
                    ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
                    ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
                    ->orderBy('sort_order')->orderBy('name')->limit(12)->get();
                if ($ids !== []) {
                    $positions = array_flip($ids);
                    $categories = $categories->sortBy(fn (Category $category) => $positions[$category->id] ?? PHP_INT_MAX)->values();
                }
                $result['categories'] = $categories;
            }

            if (in_array($block->type, ['FEATURED_PRODUCTS', 'CATEGORY_PRODUCTS'], true)) {
                $ids = array_values(array_filter((array) ($content['productIds'] ?? [])));
                $categoryIds = array_values(array_filter((array) ($content['categoryIds'] ?? [])));
                if (($categoryId = $content['categoryId'] ?? null) && $categoryIds === []) {
                    $categoryIds[] = $categoryId;
                }
                $productsQuery = Product::query()->active()->with(['images', 'category'])
                    ->whereHas('category', fn ($query) => $query->where('is_active', true));
                if ($ids !== []) {
                    $positions = array_flip($ids);
                    $products = $productsQuery->whereIn('id', $ids)->get()
                        ->sortBy(fn (Product $product) => $positions[$product->id] ?? PHP_INT_MAX)->values();
                } elseif ($categoryIds !== []) {
                    $products = $productsQuery->whereIn('category_id', $categoryIds)->latest()->limit(10)->get();
                } else {
                    $products = collect();
                }
                $result['products'] = $products;
            }

            return $result;
        });

        return view('store.home', ['banners' => $banners, 'displayBlocks' => $displayBlocks, 'homePage' => $homePage]);
    }
}
