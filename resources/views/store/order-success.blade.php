@extends('layouts.store')
@section('title', 'Order received')
@section('content')
<div class="site-width py-12"><div class="mx-auto max-w-2xl rounded-xl border border-zinc-200 bg-white p-6 text-center shadow-sm sm:p-9">
    <div class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700">✓</div><h1 class="mt-4 text-2xl font-extrabold">Thank you for your order</h1><p class="mt-2 text-sm text-zinc-600">We have received your order and will contact you to confirm delivery.</p><p class="mt-5 text-xs font-semibold uppercase tracking-wider text-zinc-400">Order number</p><p class="mt-1 text-lg font-bold text-brand">{{ $order->order_number }}</p>
    <div class="mt-6 divide-y rounded border border-zinc-200 text-left">@foreach($order->items as $item)<div class="flex justify-between gap-4 p-3 text-sm"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><strong>৳{{ number_format((float)$item->line_total,0) }}</strong></div>@endforeach<div class="flex justify-between p-3 text-sm"><span>Delivery</span><span>৳{{ number_format((float)$order->delivery_fee,0) }}</span></div><div class="flex justify-between p-3 font-bold"><span>Total</span><span>৳{{ number_format((float)$order->total,0) }}</span></div></div>
    <a href="{{ route('shop.index') }}" class="mt-6 inline-flex rounded bg-brand px-5 py-2.5 text-sm font-semibold text-white">Continue shopping</a>
</div></div>
@endsection
