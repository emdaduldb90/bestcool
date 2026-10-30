<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function success(Request $request, string $orderNumber): View
    {
        abort_unless($request->session()->get('last_order_number') === $orderNumber, 404);
        $order = Order::query()->with('items')->where('order_number', $orderNumber)->firstOrFail();

        return view('store.order-success', compact('order'));
    }
}
