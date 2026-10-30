@extends('admin.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([['Products', $stats['products'], 'admin.products.index'], ['Categories', $stats['categories'], 'admin.categories.index'], ['Orders', $stats['orders'], 'admin.orders.index'], ['Pending orders', $stats['pending'], 'admin.orders.index']] as [$label, $value, $route])
        <a href="{{ route($route) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-extrabold tracking-tight">{{ number_format($value) }}</p></a>
    @endforeach
</div>
<div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Non-cancelled order value</p><p class="mt-1 text-2xl font-extrabold">৳{{ number_format((float) $stats['orderValue'], 2) }}</p></div>

<div class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="font-bold">Recent orders</h2><a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-sky-700">All orders →</a></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[620px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($recentOrders as $order)<tr><td class="px-5 py-3"><a class="font-bold text-sky-700" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a><span class="mt-0.5 block text-xs text-slate-400">{{ $order->created_at->format('d M Y, g:i a') }}</span></td><td class="px-5 py-3">{{ $order->customer_name }}</td><td class="px-5 py-3 font-semibold">৳{{ number_format((float) $order->total, 0) }}</td><td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ $order->status }}</span></td></tr>
            @empty<tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No orders yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="font-bold">Low stock</h2><a href="{{ route('admin.products.index') }}" class="text-sm font-semibold text-sky-700">Products →</a></div>
        <div class="divide-y divide-slate-100">
            @forelse($lowStock as $product)<a href="{{ route('admin.products.edit', $product) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50"><span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $product->name }}</span><span class="text-xs text-slate-500">{{ $product->category?->name }}</span></span><span class="shrink-0 rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">{{ $product->stock }} left</span></a>
            @empty<div class="px-5 py-8 text-center text-sm text-slate-500">No low-stock products.</div>@endforelse
        </div>
    </section>
</div>
@endsection
