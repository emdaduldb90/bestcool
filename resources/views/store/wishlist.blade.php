@extends('layouts.store')
@section('title', 'Wishlist')
@section('content')
<div class="page-bg min-h-[60vh] py-8"><div class="site-width"><p class="text-xs text-zinc-500">Home / Wishlist</p><h1 class="mb-6 mt-1 text-2xl font-extrabold">My Wishlist</h1>
    @if($products->isEmpty())<div class="rounded-lg border border-zinc-200 bg-white px-6 py-14 text-center"><div class="text-4xl text-brand">♡</div><h2 class="mt-3 text-xl font-bold">Your wishlist is empty</h2><a href="{{ route('shop.index') }}" class="mt-5 inline-flex rounded bg-brand px-5 py-2.5 text-sm font-semibold text-white">Explore products</a></div>@else<div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">@foreach($products as $product)@include('store.partials.product-card',['product'=>$product])@endforeach</div>@endif
</div></div>
@endsection
