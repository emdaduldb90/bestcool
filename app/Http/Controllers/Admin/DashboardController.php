<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const STATUSES = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'];

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['nullable', 'in:today,7,30,custom'],
        ]);
        $period = $filters['period'] ?? '30';
        $to = isset($filters['to']) && $period === 'custom' ? CarbonImmutable::parse($filters['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = match ($period) {
            'today' => CarbonImmutable::now()->startOfDay(),
            '7' => $to->subDays(6)->startOfDay(),
            'custom' => isset($filters['from']) ? CarbonImmutable::parse($filters['from'])->startOfDay() : $to->subDays(29)->startOfDay(),
            default => CarbonImmutable::now()->subDays(29)->startOfDay(),
        };
        if ($from->greaterThan($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => 'Choose a valid reporting period of up to 366 days.']);
        }

        $orderBase = Order::query()->whereBetween('created_at', [$from, $to]);
        $statusCounts = (clone $orderBase)->select('status', DB::raw('COUNT(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $orders = (clone $orderBase)->where('status', '!=', 'CANCELLED');
        $stats = [
            'orders' => (clone $orderBase)->count(),
            'pending' => (int) ($statusCounts['PENDING'] ?? 0),
            'processing' => (int) ($statusCounts['PROCESSING'] ?? 0),
            'shipped' => (int) ($statusCounts['SHIPPED'] ?? 0),
            'delivered' => (int) ($statusCounts['DELIVERED'] ?? 0),
            'cancelled' => (int) ($statusCounts['CANCELLED'] ?? 0),
            'activeProducts' => Product::query()->where('is_active', true)->count(),
            'categories' => Category::query()->where('is_active', true)->count(),
            'customers' => User::query()->where('is_admin', false)->whereDoesntHave('roles')->count(),
            'lowStock' => Product::query()->where('is_active', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            'outOfStock' => Product::query()->where('is_active', true)->where('stock', 0)->count(),
            'orderValue' => (float) $orders->sum('total'),
            'deliveredValue' => (float) (clone $orderBase)->where('status', 'DELIVERED')->sum('total'),
            'averageOrderValue' => (float) $orders->avg('total'),
        ];

        $daily = (clone $orderBase)->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(CASE WHEN status != ? THEN total ELSE 0 END) as order_value', ['CANCELLED'])
            ->groupByRaw('DATE(created_at)')->orderByRaw('DATE(created_at)')->get();
        $statusBreakdown = collect(self::STATUSES)->map(fn ($status) => ['status' => $status, 'count' => (int) ($statusCounts[$status] ?? 0)])->all();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'CANCELLED')
            ->select('order_items.product_id', 'order_items.product_name', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.line_total) as order_value'))
            ->groupBy('order_items.product_id', 'order_items.product_name')->orderByDesc('units')->limit(5)->get();

        $categorySales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'CANCELLED')
            ->select('categories.name', DB::raw('SUM(order_items.line_total) as order_value'))
            ->groupBy('categories.id', 'categories.name')->orderByDesc('order_value')->limit(6)->get();

        $recentOrders = (clone $orderBase)->latest()->limit(8)->get();
        $lowStockProducts = Product::query()->with('category')->where('is_active', true)
            ->whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(6)->get();

        return view('admin.dashboard', compact('stats', 'statusBreakdown', 'daily', 'topProducts', 'categorySales', 'recentOrders', 'lowStockProducts', 'from', 'to'));
    }
}
