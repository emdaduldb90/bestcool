@extends('layouts.store')
@section('title', 'Checkout')
@section('content')
<div class="page-bg min-h-[60vh] py-8"><div class="site-width">
    <p class="text-xs text-zinc-500">Home / Cart / Checkout</p><h1 class="mb-6 mt-1 text-2xl font-extrabold">Checkout</h1>
    @if($errors->any())<div class="mb-5 rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><p class="font-bold">Please check the details below.</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('checkout.store') }}" method="POST" class="grid items-start gap-5 lg:grid-cols-[1fr_360px]">@csrf
        <section class="space-y-5">
            <div class="rounded-lg border border-zinc-200 bg-white p-5 sm:p-6">
                <h2 class="mb-4 text-lg font-bold">Delivery details</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold sm:col-span-2">Full name<input name="customer_name" value="{{ old('customer_name', auth()->user()->name ?? '') }}" required maxlength="120" autocomplete="name" class="mt-1.5 w-full rounded border border-zinc-300 px-3 py-2.5 font-normal"></label>
                    <label class="text-sm font-semibold">Phone number<input name="phone" value="{{ old('phone') }}" required maxlength="32" autocomplete="tel" class="mt-1.5 w-full rounded border border-zinc-300 px-3 py-2.5 font-normal"></label>
                    <label class="text-sm font-semibold">Email (optional)<input name="email" type="email" value="{{ old('email', auth()->user()->email ?? '') }}" maxlength="190" autocomplete="email" class="mt-1.5 w-full rounded border border-zinc-300 px-3 py-2.5 font-normal"></label>
                    <label class="text-sm font-semibold sm:col-span-2">Delivery address<textarea name="address" rows="3" required maxlength="1000" autocomplete="street-address" class="mt-1.5 w-full rounded border border-zinc-300 px-3 py-2.5 font-normal">{{ old('address') }}</textarea></label>
                    <label class="text-sm font-semibold">District<input name="district" value="{{ old('district') }}" maxlength="120" autocomplete="address-level1" class="mt-1.5 w-full rounded border border-zinc-300 px-3 py-2.5 font-normal"></label>
                    <label class="text-sm font-semibold">Delivery area<select name="delivery_zone_id" required class="mt-1.5 w-full rounded border border-zinc-300 bg-white px-3 py-2.5 font-normal"><option value="">Choose an area</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected(old('delivery_zone_id') === $zone->id)>{{ $zone->name }} — ৳{{ number_format((float)$zone->charge,0) }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold sm:col-span-2">Order note (optional)<textarea name="notes" rows="2" maxlength="1000" class="mt-1.5 w-full rounded border border-zinc-300 px-3 py-2.5 font-normal">{{ old('notes') }}</textarea></label>
                </div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-5 sm:p-6"><h2 class="text-lg font-bold">Payment method</h2><label class="mt-4 flex items-center gap-3 rounded border border-brand bg-brand-soft p-4"><input type="radio" checked disabled><span><strong class="block text-sm">Cash on Delivery</strong><small class="text-xs text-zinc-600">Pay when your order arrives.</small></span></label><input type="hidden" name="payment_method" value="COD"></div>
        </section>
        <aside class="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 class="text-lg font-bold">Your order</h2>
            <div class="mt-4 space-y-3">@foreach($cart as $id => $quantity) @php($product = $items->get($id)) @if($product)<div class="flex justify-between gap-3 text-sm"><span class="text-zinc-600">{{ $product->name }} × {{ $quantity }}</span><strong>৳{{ number_format((float)$product->price * (int)$quantity,0) }}</strong></div>@endif @endforeach</div>
            <hr class="my-4"><div class="flex justify-between text-sm text-zinc-600"><span>Subtotal</span><span>৳{{ number_format($subtotal,0) }}</span></div><div class="mt-2 flex justify-between text-sm text-zinc-600"><span>Delivery</span><span>Area rate</span></div><p class="mt-2 text-xs leading-5 text-zinc-400">Delivery charge is set by your selected area.</p><button class="mt-5 w-full rounded bg-brand px-4 py-3 text-sm font-bold text-white hover:bg-brand-dark">Place Order · Cash on Delivery</button><p class="mt-3 text-center text-[11px] text-zinc-400">Your information is used only to process this order.</p>
        </aside>
    </form>
</div></div>
@endsection
