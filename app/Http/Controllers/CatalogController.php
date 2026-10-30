<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->active()->with(['images', 'category'])
            ->whereHas('category', fn ($builder) => $builder->where('is_active', true));

        if ($term = trim((string) $request->query('search'))) {
            $query->where(function ($search) use ($term) {
                $search->where('name', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($builder) => $builder->where('slug', $category)->where('is_active', true));
        }

        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }

        match ($request->query('sort')) {
            'price-low' => $query->orderByRaw('COALESCE(sale_price, regular_price) ASC'),
            'price-high' => $query->orderByRaw('COALESCE(sale_price, regular_price) DESC'),
            'popular' => $query->orderByDesc('sold_count'),
            default => $query->orderByDesc('created_at'),
        };

        $products = $query->paginate(20)->withQueryString();
        $brands = Product::query()->active()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand');
        $activeCategory = $request->query('category');
        $category = $activeCategory ? Category::query()->where('slug', $activeCategory)->where('is_active', true)->first() : null;
        $shopPage = ContentPage::query()->where('slug', 'shop')->first(['meta_title', 'meta_description', 'og_image']);

        return view('store.shop', compact('products', 'brands', 'activeCategory', 'category', 'shopPage'));
    }

    public function show(string $slug): View
    {
        $product = Product::query()->active()->with(['images', 'category'])->whereHas('category', fn ($query) => $query->where('is_active', true))->where('slug', $slug)->firstOrFail();
        $related = Product::query()->active()->with(['images', 'category'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(8)
            ->get();

        return view('store.product', compact('product', 'related'));
    }
}
