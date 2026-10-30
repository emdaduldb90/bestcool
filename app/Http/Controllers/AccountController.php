<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()->with('items')->where('user_id', Auth::id())->latest()->paginate(10);

        return view('store.account', compact('orders'));
    }
}
