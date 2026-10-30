<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $cart = session('cart', []);
        if ($cart === []) {
            return redirect()->route('shop.index')->with('error', 'Your cart is empty.');
        }

        $items = Product::query()->active()->with('images')->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $subtotal = collect($cart)->sum(fn ($quantity, $id) => (float) ($items->get($id)?->price ?? 0) * (int) $quantity);
        $zones = DeliveryZone::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('store.checkout', compact('items', 'cart', 'subtotal', 'zones'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['required', 'string', 'max:1000'],
            'district' => ['nullable', 'string', 'max:120'],
            'delivery_zone_id' => ['required', 'exists:delivery_zones,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = session('cart', []);
        if ($cart === []) {
            return redirect()->route('shop.index')->with('error', 'Your cart is empty.');
        }

        $zone = DeliveryZone::query()->where('is_active', true)->find($data['delivery_zone_id']);
        if (! $zone) {
            throw ValidationException::withMessages(['delivery_zone_id' => 'Select an available delivery area.']);
        }

        $order = DB::transaction(function () use ($data, $cart, $zone) {
            $products = Product::query()->with('images')->whereIn('id', array_keys($cart))->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count($cart)) {
                throw ValidationException::withMessages(['cart' => 'A cart item is no longer available. Please review your cart.']);
            }

            $subtotal = 0.0;
            foreach ($cart as $id => $quantity) {
                $product = $products->get($id);
                if (! $product || ! $product->is_active || $product->stock < (int) $quantity) {
                    throw ValidationException::withMessages(['cart' => 'Stock changed for '.($product?->name ?? 'a cart item').'. Please review your cart.']);
                }
                $subtotal += (float) $product->price * (int) $quantity;
            }

            $order = Order::query()->create([
                'user_id' => Auth::id(),
                'order_number' => 'BCE-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'customer_name' => $data['customer_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'],
                'district' => $data['district'] ?? null,
                'delivery_zone_id' => $zone->id,
                'subtotal' => $subtotal,
                'delivery_fee' => $zone->charge,
                'total' => $subtotal + (float) $zone->charge,
                'status' => 'PENDING',
                'payment_method' => 'COD',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($cart as $id => $quantity) {
                $product = $products->get($id);
                $lineTotal = (float) $product->price * (int) $quantity;
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'image_url' => $product->images->first()?->url,
                    'unit_price' => $product->price,
                    'quantity' => (int) $quantity,
                    'line_total' => $lineTotal,
                ]);
                $product->decrement('stock', (int) $quantity);
                $product->increment('sold_count', (int) $quantity);
            }

            return $order;
        });

        session()->forget('cart');
        session(['last_order_number' => $order->order_number]);

        return redirect()->route('order.success', $order->order_number);
    }
}
