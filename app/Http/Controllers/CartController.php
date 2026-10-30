<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        $cart = session('cart', []);
        $products = Product::query()->with('images')->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $items = collect($cart)->map(function ($quantity, $id) use ($products) {
            $product = $products->get($id);

            return $product ? ['product' => $product, 'quantity' => (int) $quantity, 'lineTotal' => (float) $product->price * (int) $quantity] : null;
        })->filter()->values();
        $subtotal = $items->sum('lineTotal');

        return view('store.cart', compact('items', 'subtotal'));
    }

    public function store(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->is_active, 404);
        $quantity = max(1, (int) $request->input('quantity', 1));

        if ($product->stock < 1) {
            return $request->expectsJson()
                ? response()->json(['message' => 'This product is out of stock.'], 422)
                : back()->with('error', 'This product is out of stock.');
        }

        $cart = session('cart', []);
        $cart[$product->id] = min($product->stock, (int) ($cart[$product->id] ?? 0) + $quantity);
        session(['cart' => $cart]);

        return $request->expectsJson()
            ? response()->json(['cartCount' => array_sum($cart), 'message' => 'Added to your cart.'])
            : back()->with('success', 'Added to your cart.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        abort_unless($product->is_active, 404);

        if ($data['quantity'] > $product->stock) {
            return back()->withErrors(['quantity' => 'Only '.$product->stock.' item(s) are currently available.']);
        }

        $cart = session('cart', []);
        $cart[$product->id] = (int) $data['quantity'];
        session(['cart' => $cart]);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $cart = session('cart', []);
        unset($cart[$product->id]);
        session(['cart' => $cart]);

        return back()->with('success', 'Item removed from your cart.');
    }
}
