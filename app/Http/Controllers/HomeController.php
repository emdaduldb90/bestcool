<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $blocks = ContentPage::query()
            ->where('slug', 'home')
            ->with(['blocks' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
            ->first()?->blocks ?? collect();

        $hero = $blocks->first(fn ($block) => $block->type === 'BANNER' && ! empty($block->content['image']));
        $testimonials = $blocks->firstWhere('type', 'TESTIMONIALS');
        $sections = $blocks
            ->whereIn('type', ['FEATURED_PRODUCTS', 'CATEGORY_PRODUCTS'])
            ->map(function ($block) {
                $ids = $block->content['productIds'] ?? [];
                if (! is_array($ids) || $ids === []) {
                    return null;
                }

                $position = array_flip($ids);
                $products = Product::query()
                    ->active()
                    ->with(['images', 'category'])
                    ->whereIn('id', $ids)
                    ->get()
                    ->sortBy(fn (Product $product) => $position[$product->id] ?? PHP_INT_MAX)
                    ->values();

                return $products->isEmpty() ? null : ['block' => $block, 'products' => $products];
            })
            ->filter()
            ->values();

        return view('store.home', compact('hero', 'sections', 'testimonials'));
    }
}
