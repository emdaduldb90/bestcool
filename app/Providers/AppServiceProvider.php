<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.store', 'store.*'], function ($view): void {
            $settings = Cache::remember('storefront.settings', 3600, fn () => Setting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value])
                ->all());

            $categories = Cache::remember('storefront.categories', 600, fn () => Category::query()
                ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('name')
                ->get());

            $cart = session('cart', []);
            $view->with([
                'siteSettings' => $settings,
                'siteCategories' => $categories,
                'cartCount' => array_sum(array_map('intval', $cart)),
                'wishlistCount' => count(session('wishlist', [])),
            ]);
        });
    }
}
