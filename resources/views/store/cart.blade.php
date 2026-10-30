@extends('layouts.store')
@section('title', 'Shopping Cart')
@section('content')
<div class="page-bg min-h-[60vh] py-8"><div class="site-width">
    <p class="text-xs text-zinc-500">Home / Cart</p><h1 class="mb-6 mt-1 text-2xl font-extrabold">Shopping Cart</h1>
    @if($items->isEmpty())
        <div class="rounded-lg border border-zinc-200 bg-white px-6 py-16 text-center"><div class="text-5xl">🛒</div><h2 class="mt-4 text-xl font-bold">Your cart is empty</h2><p class="mt-2 text-sm text-zinc-500">Browse our collection and find something for your home.</p><a href="{{ route('shop.index') }}" class="mt-5 inline-flex rounded bg-brand px-5 py-2.5 text-sm font-semibold text-white">Continue shopping</a></div>
    @else
        <div class="grid items-start gap-5 lg:grid-cols-[1fr_320px]">
            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                @foreach($items as $item)
                    @php($product = $item['product'])
                    <div class="flex flex-wrap items-center gap-4 border-b border-zinc-100 p-4 last:border-0 sm:flex-nowrap">
                        <a href="{{ route('product.show', $product) }}" class="grid size-20 shrink-0 place-items-center overflow-hidden rounded bg-zinc-50">@if($product->primary_image)<img src="{{ filter_var($product->primary_image,FILTER_VALIDATE_URL) ? $product->primary_image : asset(ltrim($product->primary_image,'/')) }}" alt="{{ $product->name }}" class="size-full object-contain" loading="lazy">@endif</a>
                        <div class="min-w-0 flex-1"><a href="{{ route('product.show', $product) }}" class="font-semibold hover:text-brand">{{ $product->name }}</a><p class="mt-1 text-sm text-zinc-500">৳{{ number_format((float)$product->price,0) }} each</p></div>
                        <form action="{{ route('cart.update', $product) }}" method="POST" class="flex items-center gap-2">@csrf @method('PUT')<label class="sr-only" for="qty-{{ $product->id }}">Quantity</label><input id="qty-{{ $product->id }}" type="number" name="quantity" min="1" max="{{ min(99,$product->stock) }}" value="{{ $item['quantity'] }}" class="w-16 rounded border border-zinc-300 px-2 py-2 text-center text-sm"><button class="rounded border border-zinc-300 px-3 py-2 text-xs font-semibold hover:bg-zinc-50">Update</button></form>
                        <strong class="w-24 text-right">৳{{ number_format($item['lineTotal'],0) }}</strong>
                        <form action="{{ route('cart.destroy', $product) }}" method="POST">@csrf @method('DELETE')<button class="px-2 text-lg text-zinc-400 hover:text-rose-600" aria-label="Remove {{ $product->name }}">×</button></form>
                    </div>
                @endforeach
            </div>
            <aside class="rounded-lg border border-zinc-200 bg-white p-5">
                <h2 class="text-lg font-bold">Order Summary</h2><div class="mt-4 flex justify-between text-sm text-zinc-600"><span>Subtotal</span><span>৳{{ number_format($subtotal,0) }}</span></div><div class="mt-3 flex justify-between text-sm text-zinc-600"><span>Delivery</span><span>Calculated at checkout</span></div><hr class="my-4"><div class="flex justify-between font-bold"><span>Total</span><span>৳{{ number_format($subtotal,0) }}</span></div>
                <a href="{{ route('checkout.create') }}" class="mt-5 block rounded bg-brand px-4 py-3 text-center text-sm font-bold text-white hover:bg-brand-dark">Proceed to Checkout</a><a href="{{ route('shop.index') }}" class="mt-3 block text-center text-xs text-zinc-500 underline">Continue shopping</a>
            </aside>
        </div>
    @endif
</div></div>
@endsection
