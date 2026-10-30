<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = $this->range($filters);
        $orders = Order::query()->whereBetween('created_at', [$from->startOfDay(), $to->copy()->endOfDay()]);
        $summary = (clone $orders)->selectRaw("COUNT(*) as total_orders, SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled, SUM(CASE WHEN status != 'CANCELLED' THEN total ELSE 0 END) as order_value, SUM(CASE WHEN status = 'DELIVERED' THEN total ELSE 0 END) as delivered_value")->first();
        $daily = (clone $orders)->selectRaw('DATE(created_at) as day, COUNT(*) as order_count, SUM(CASE WHEN status != ? THEN total ELSE 0 END) as order_value', ['CANCELLED'])->groupBy(DB::raw('DATE(created_at)'))->orderBy('day')->get();
        $topProducts = OrderItem::query()->select('order_items.product_id', 'order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) as units_sold, SUM(order_items.line_total) as order_value')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')->whereBetween('orders.created_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->where('orders.status', '!=', 'CANCELLED')->groupBy('order_items.product_id', 'order_items.product_name')->orderByDesc('units_sold')->limit(10)->get();
        $categorySales = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$from->startOfDay(), $to->copy()->endOfDay()])->where('orders.status', '!=', 'CANCELLED')
            ->select('categories.name')->selectRaw('SUM(order_items.line_total) as order_value')->groupBy('categories.id', 'categories.name')->orderByDesc('order_value')->limit(10)->get();
        $lowStock = Product::query()->with('category')->whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(20)->get();
        return view('admin.reports.index', compact('filters', 'from', 'to', 'summary', 'daily', 'topProducts', 'categorySales', 'lowStock'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = $this->range($filters);
        $filename = 'orders-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';
        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Order number', 'Customer', 'Phone', 'Email', 'Date', 'Status', 'Payment method', 'Subtotal BDT', 'Delivery BDT', 'Total BDT']);
            Order::query()->whereBetween('created_at', [$from->startOfDay(), $to->copy()->endOfDay()])->orderBy('id')->chunk(500, function ($orders) use ($out) {
                foreach ($orders as $order) fputcsv($out, [$order->order_number, $order->customer_name, $order->phone, $order->email, $order->created_at?->toDateTimeString(), $order->status, $order->payment_method, $order->subtotal, $order->delivery_fee, $order->total]);
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(array $filters): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : now()->subDays(29);
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : now();
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => 'Choose a report period of 366 days or less.']);
        }
        return [$from->startOfDay(), $to->startOfDay()];
    }
}
