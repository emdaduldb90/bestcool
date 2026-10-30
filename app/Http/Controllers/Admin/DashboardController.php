<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'products' => Product::query()->count(),
            'categories' => Category::query()->count(),
            'orders' => Order::query()->count(),
            'pending' => Order::query()->where('status', 'PENDING')->count(),
            'orderValue' => Order::query()->where('status', '!=', 'CANCELLED')->sum('total'),
        ];
        $recentOrders = Order::query()->latest()->limit(8)->get();
        $lowStock = Product::query()->with('category')->where('is_active', true)
            ->whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(6)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'lowStock'));
    }
}
