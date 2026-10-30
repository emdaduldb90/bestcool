<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const STATUSES = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'];

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim($request->string('search')->toString());
                $query->where(fn ($builder) => $builder->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"));
            })
            ->latest()->paginate(20)->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'statuses' => self::STATUSES]);
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'user']);

        return view('admin.orders.show', ['order' => $order, 'statuses' => self::STATUSES]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', self::STATUSES)]]);

        if ($order->status === 'CANCELLED' && $data['status'] !== 'CANCELLED') {
            return back()->with('error', 'Cancelled orders cannot be reopened.');
        }

        DB::transaction(function () use ($order, $data) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($data['status'] === 'CANCELLED' && $lockedOrder->status !== 'CANCELLED') {
                $lockedOrder->load('items');
                foreach ($lockedOrder->items as $item) {
                    $product = $item->product_id
                        ? Product::query()->lockForUpdate()->find($item->product_id)
                        : null;
                    if (! $product) {
                        continue;
                    }
                    $product->stock += $item->quantity;
                    $product->sold_count = max(0, $product->sold_count - $item->quantity);
                    $product->save();
                }
            }
            $lockedOrder->update(['status' => $data['status']]);
        });

        return back()->with('success', 'Order status updated.');
    }
}
