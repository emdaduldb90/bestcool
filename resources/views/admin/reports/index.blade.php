@extends('admin.layout')
@section('page-title', 'Reports')
@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <p class="text-sm text-slate-500">Order and fulfillment data from {{ $from->format('d M Y') }} through {{ $to->format('d M Y') }}.</p>
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <label class="text-xs font-semibold">From<input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}" class="admin-input mt-1"></label>
        <label class="text-xs font-semibold">To<input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" class="admin-input mt-1"></label>
        <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-bold text-white">Apply</button>
        <a href="{{ route('admin.reports.export', request()->query()) }}" class="rounded-lg border bg-white px-4 py-2.5 text-xs font-bold">Export CSV</a>
    </form>
</div>
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Orders</p><strong class="mt-2 block text-2xl">{{ number_format($summary->total_orders ?? 0) }}</strong></article>
    <article class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Non-cancelled order value (BDT)</p><strong class="mt-2 block text-2xl">{{ number_format((float)($summary->order_value ?? 0), 2) }}</strong></article>
    <article class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Delivered order value (BDT)</p><strong class="mt-2 block text-2xl">{{ number_format((float)($summary->delivered_value ?? 0), 2) }}</strong></article>
    <article class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Cancelled orders</p><strong class="mt-2 block text-2xl">{{ number_format($summary->cancelled ?? 0) }}</strong></article>
</div>
<p class="mt-3 text-xs text-slate-500">Delivered value reflects order status. COD payments are not independently captured or verified. Report periods are limited to 366 days.</p>
<div class="mt-5 grid gap-5 xl:grid-cols-2">
    <section class="rounded-xl border bg-white p-5"><h2 class="font-bold">Daily activity</h2><div class="mt-3 divide-y">
        @forelse($daily as $day)<div class="flex justify-between py-2 text-sm"><span>{{ \Illuminate\Support\Carbon::parse($day->day)->format('d M Y') }} · {{ $day->order_count }} orders</span><strong>BDT {{ number_format((float)$day->order_value, 2) }}</strong></div>
        @empty<p class="py-5 text-sm text-slate-500">No orders in this date range.</p>@endforelse
    </div></section>
    <section class="rounded-xl border bg-white p-5"><h2 class="font-bold">Top products</h2><div class="mt-3 divide-y">
        @forelse($topProducts as $product)<div class="flex justify-between gap-3 py-2 text-sm"><span>{{ $product->product_name }}</span><strong>{{ $product->units_sold }} units · BDT {{ number_format((float)$product->order_value, 2) }}</strong></div>
        @empty<p class="py-5 text-sm text-slate-500">No product sales in this date range.</p>@endforelse
    </div></section>
    <section class="rounded-xl border bg-white p-5"><h2 class="font-bold">Top categories</h2><div class="mt-3 divide-y">
        @forelse($categorySales as $category)<div class="flex justify-between py-2 text-sm"><span>{{ $category->name }}</span><strong>BDT {{ number_format((float)$category->order_value, 2) }}</strong></div>
        @empty<p class="py-5 text-sm text-slate-500">No category sales in this date range.</p>@endforelse
    </div></section>
    <section class="rounded-xl border bg-white p-5"><h2 class="font-bold">Low and out of stock</h2><div class="mt-3 grid gap-2">
        @forelse($lowStock as $product)<div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"><span>@if(auth()->user()->hasPermission('inventory.manage'))<a href="{{ route('admin.inventory.index', ['search' => $product->sku ?: $product->name]) }}" class="font-semibold text-sky-700">{{ $product->name }}</a>@else{{ $product->name }}@endif</span><strong class="{{ $product->stock ? 'text-amber-700' : 'text-rose-700' }}">{{ $product->stock }} left</strong></div>
        @empty<p class="text-sm text-slate-500">No stock alerts.</p>@endforelse
    </div></section>
</div>
@endsection
