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
            $settings += [
                'home.about.heading' => 'Everyday essentials. A better place to call home.',
                'home.about.html' => '<p>Explore air conditioners, kitchen appliances and home electronics from the brands you know. Compare your options, discover useful upgrades and choose what works for your home, your family and your budget.</p><h3>Comfort, for every season</h3><p>Find cooling solutions for your space and get in touch for help choosing.</p><h3>Make more of the everyday</h3><p>Discover practical kitchen and home appliances for your family.</p><h3>A team you can talk to</h3><p>Have a question about a product or order? Contact us or visit our showroom.</p>',
                'home.services' => [
                    ['icon' => '⌕', 'title' => 'Product Finder', 'text' => 'Find the right fit for your home', 'href' => '/shop'],
                    ['icon' => '☎', 'title' => 'Talk to an Expert', 'text' => 'A little help choosing better', 'href' => ! empty($settings['contact.phone']) ? 'tel:'.$settings['contact.phone'] : '/contact-us'],
                    ['icon' => '❄', 'title' => 'AC Collection', 'text' => 'Comfort for every space', 'href' => '/shop?category=air-conditioner'],
                    ['icon' => '◉', 'title' => 'Customer Support', 'text' => 'We are here after your purchase', 'href' => '/contact-us'],
                ],
            ];

            $categories = Cache::remember('storefront.categories', 600, fn () => Category::query()
                ->where('is_active', true)
                ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')->orderBy('name')
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
