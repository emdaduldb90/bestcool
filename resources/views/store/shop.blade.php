@extends('layouts.store')
@section('title', $category?->meta_title ?: ($shopPage?->meta_title ?: 'Shop | '.($siteSettings['site.name'] ?? 'Best Cool Electronics')))
@section('description', $category?->meta_description ?: ($shopPage?->meta_description ?: ($siteSettings['seo.defaultDescription'] ?? '')))
@if($category?->image) @section('og_image', filter_var($category->image, FILTER_VALIDATE_URL) ? $category->image : url(ltrim($category->image, '/'))) @elseif($shopPage?->og_image) @section('og_image', filter_var($shopPage->og_image, FILTER_VALIDATE_URL) ? $shopPage->og_image : url(ltrim($shopPage->og_image, '/'))) @endif

@section('content')
<div class="page-bg min-h-[65vh] py-7">
    <div class="site-width" @if(request()->filled('search')) data-track-search data-search-term="{{ request('search') }}" data-result-count="{{ $products->total() }}" @endif>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs text-zinc-500">Home / Shop</p><h1 class="mt-1 text-2xl font-extrabold">{{ request('search') ? 'Search results' : 'Shop' }}</h1>@if(request('search'))<p class="mt-1 text-sm text-zinc-500">Results for “{{ request('search') }}”</p>@endif</div>
            <span class="text-sm text-zinc-500">{{ $products->total() }} products</span>
        </div>
        <div class="grid items-start gap-5 lg:grid-cols-[230px_1fr]">
            <aside class="rounded-lg border border-zinc-200 bg-white p-4">
                <form action="{{ route('shop.index') }}" method="GET" class="space-y-4">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    <label class="block text-sm font-bold">Category
                        <select name="category" class="mt-2 w-full rounded border border-zinc-300 bg-white px-3 py-2 text-sm">
                            <option value="">All categories</option>
                            @foreach($siteCategories as $category)<option value="{{ $category->slug }}" @selected($activeCategory === $category->slug)>{{ $category->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-bold">Brand
                        <select name="brand" class="mt-2 w-full rounded border border-zinc-300 bg-white px-3 py-2 text-sm">
                            <option value="">All brands</option>
                            @foreach($brands as $brand)<option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-bold">Sort by
                        <select name="sort" class="mt-2 w-full rounded border border-zinc-300 bg-white px-3 py-2 text-sm">
                            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option><option value="popular" @selected(request('sort') === 'popular')>Popular</option><option value="price-low" @selected(request('sort') === 'price-low')>Price: low to high</option><option value="price-high" @selected(request('sort') === 'price-high')>Price: high to low</option>
                        </select>
                    </label>
                    <button class="w-full rounded bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-dark">Apply filters</button>
                    <a href="{{ route('shop.index') }}" class="block text-center text-xs text-zinc-500 underline">Clear all</a>
                </form>
            </aside>
            <section>
                @if($products->isNotEmpty())
                    <div class="grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3 xl:grid-cols-4">
                        @foreach($products as $product) @include('store.partials.product-card', ['product' => $product]) @endforeach
                    </div>
                    <div class="mt-7">{{ $products->links() }}</div>
                @else
                    <div class="rounded-lg border border-zinc-200 bg-white px-6 py-16 text-center"><h2 class="text-xl font-bold">No products found</h2><p class="mt-2 text-sm text-zinc-500">Try a different search or filter.</p><a href="{{ route('shop.index') }}" class="mt-5 inline-flex rounded bg-brand px-5 py-2.5 text-sm font-semibold text-white">Browse all products</a></div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
