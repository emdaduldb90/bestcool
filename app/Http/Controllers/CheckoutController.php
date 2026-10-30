<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\OrderStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\StockLedger;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Services\Tracking\TrackingEventService;
use Throwable;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function create(Request $request, TrackingEventService $tracking): View|RedirectResponse
    {
        $cart = session('cart', []);
        if ($cart === []) {
            return redirect()->route('shop.index')->with('error', 'Your cart is empty.');
        }

        $items = Product::query()->active()->with('images')->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $subtotal = collect($cart)->sum(fn ($quantity, $id) => (float) ($items->get($id)?->price ?? 0) * (int) $quantity);
        $zones = DeliveryZone::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $checkoutEvent = $tracking->captureTrusted($request, 'InitiateCheckout', ['value' => $subtotal, 'currency' => 'BDT']);
        $trackingCheckoutEventId = $checkoutEvent?->event_id;

        return view('store.checkout', compact('items', 'cart', 'subtotal', 'zones', 'trackingCheckoutEventId'));
    }

    public function store(Request $request, TrackingEventService $tracking): RedirectResponse
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
            $products = Product::query()->with('images')->whereIn('id', array_keys($cart))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
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
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'actor_id' => Auth::id(),
                'from_status' => null,
                'to_status' => 'PENDING',
                'note' => 'Order placed',
                'created_at' => now(),
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
                $previousStock = (int) $product->stock;
                $product->decrement('stock', (int) $quantity);
                $product->increment('sold_count', (int) $quantity);
                StockLedger::record($product, $previousStock, -(int) $quantity, 'ORDER', 'Reserved for order '.$order->order_number);
            }

            return $order;
        });

        try {
            $tracking->captureTrusted($request, 'Purchase', [
                'order_id' => $order->id,
                'value' => (float) $order->total,
                'currency' => 'BDT',
                'items' => $order->items()->get(['product_id', 'quantity', 'unit_price'])->map(fn ($item) => [
                    'item_id' => (string) $item->product_id, 'quantity' => (int) $item->quantity, 'price' => (float) $item->unit_price,
                ])->all(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Tracking event capture failed after order placement.', ['event' => 'Purchase', 'order_id' => $order->id]);
        }

        session()->forget('cart');
        session(['last_order_number' => $order->order_number]);

        return redirect()->route('order.success', $order->order_number);
    }
}
