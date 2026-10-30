@extends('layouts.store')
@section('title', $product->meta_title ?: $product->name.' | '.($siteSettings['site.name'] ?? 'Best Cool Electronics'))
@section('description', $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($product->description ?? ''), 300))
@section('og_image', $product->images->first()?->url ? (filter_var($product->images->first()->url, FILTER_VALIDATE_URL) ? $product->images->first()->url : url(ltrim($product->images->first()->url, '/'))) : '')

@section('content')
@php($assetUrl = fn ($path) => filter_var($path, FILTER_VALIDATE_URL) ? $path : asset(ltrim($path, '/')))
<div class="site-width py-7" data-track-view-content data-product-id="{{ $product->id }}" data-product-sku="{{ $product->sku }}" data-value="{{ $product->price }}" data-currency="BDT">
    <p class="mb-5 text-xs text-zinc-500"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('shop.index') }}">Shop</a> / {{ $product->name }}</p>
    <div class="grid items-start gap-8 md:grid-cols-2">
        <div>
            @if($product->images->isNotEmpty())
                <div class="grid aspect-square place-items-center overflow-hidden rounded-lg border border-zinc-200 bg-white p-4"><img data-main-product-image src="{{ $assetUrl($product->images->first()->url) }}" alt="{{ $product->images->first()->alt ?: $product->name }}" class="max-h-full w-full object-contain" width="800" height="800" fetchpriority="high"></div>
                @if($product->images->count() > 1)<div class="mt-3 flex gap-2 overflow-x-auto">@foreach($product->images as $image)<button type="button" data-product-image="{{ $assetUrl($image->url) }}" class="size-16 shrink-0 overflow-hidden rounded border border-zinc-200 bg-white p-1 hover:border-brand"><img src="{{ $assetUrl($image->url) }}" alt="{{ $image->alt ?: $product->name }}" class="size-full object-contain" loading="lazy" width="64" height="64"></button>@endforeach</div>@endif
            @else
                <div class="grid aspect-square place-items-center rounded-lg bg-zinc-100 text-zinc-400">Product image coming soon</div>
            @endif
        </div>
        <div>
            @if($product->category)<a href="{{ route('shop.index', ['category' => $product->category->slug]) }}" class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $product->category->name }}</a>@endif
            <h1 class="mt-2 text-2xl font-extrabold leading-tight text-ink sm:text-3xl">{{ $product->name }}</h1>
            @if($product->brand)<p class="mt-2 text-sm text-zinc-500">Brand: <span class="font-semibold text-zinc-800">{{ $product->brand }}</span></p>@endif
            @if($product->sku)<p class="mt-1 text-xs text-zinc-400">SKU: {{ $product->sku }}</p>@endif
            <div class="mt-5 flex items-center gap-3">
                <span class="text-2xl font-bold text-[#0050d0]">৳{{ number_format((float)$product->price, 0) }}</span>
                @if($product->sale_price && (float)$product->sale_price < (float)$product->regular_price)<del class="text-sm text-zinc-400">৳{{ number_format((float)$product->regular_price,0) }}</del><span class="rounded bg-rose-50 px-2 py-1 text-xs font-bold text-rose-600">Save {{ round((1-(float)$product->sale_price/(float)$product->regular_price)*100) }}%</span>@endif
            </div>
            <p class="mt-3 text-sm {{ $product->stock > 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $product->stock > 0 ? 'In stock' : 'Out of stock' }}</p>
            <form action="{{ route('cart.store', $product) }}" method="POST" data-cart-form class="mt-5 flex max-w-md items-center gap-3">@csrf
                <label for="quantity" class="sr-only">Quantity</label><input id="quantity" type="number" name="quantity" min="1" max="{{ min(99, $product->stock) }}" value="1" class="w-20 rounded border border-zinc-300 px-3 py-3 text-center" @disabled($product->stock < 1)>
                <button type="submit" class="flex-1 rounded bg-brand px-5 py-3 font-bold text-white hover:bg-brand-dark" @disabled($product->stock < 1)>Add To Cart</button>
            </form>
            <div class="mt-6 rounded-lg bg-brand-soft p-4 text-sm text-ink"><p class="font-bold">Genuine products with official warranty</p><p class="mt-1 text-zinc-600">Cash on delivery and delivery across Bangladesh.</p></div>
            <div class="mt-7 border-t border-zinc-200 pt-5">
                <h2 class="text-lg font-bold">Description</h2><div class="content-rich mt-3">{!! \App\Support\SafeHtml::clean($product->description) !!}</div>
            </div>
            @if($product->specifications)<div class="mt-6 border-t border-zinc-200 pt-5"><h2 class="text-lg font-bold">Specifications</h2><div class="content-rich mt-3">{!! \App\Support\SafeHtml::clean($product->specifications) !!}</div></div>@endif
            @if(count($product->questions ?? []))<section class="mt-6 border-t border-zinc-200 pt-5" aria-labelledby="product-faq"><h2 id="product-faq" class="text-lg font-bold">Product questions</h2><div class="mt-3 divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white">@foreach($product->questions as $item)@if(!empty($item['question']) && !empty($item['answer']))<details class="group p-4"><summary class="cursor-pointer font-semibold">{{ $item['question'] }}</summary><div class="content-rich pt-3">{!! \App\Support\SafeHtml::clean($item['answer']) !!}</div></details>@endif @endforeach</div></section>@endif
        </div>
    </div>
    @if($related->isNotEmpty())
        <section class="mt-12"><h2 class="section-heading mb-5">Related Products</h2><div class="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-5">@foreach($related as $relatedProduct) @include('store.partials.product-card', ['product' => $relatedProduct]) @endforeach</div></section>
    @endif
</div>
@endsection
