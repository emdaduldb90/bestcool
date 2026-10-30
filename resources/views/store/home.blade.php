@extends('layouts.store')

@section('title', $homePage?->meta_title ?: ($siteSettings['seo.defaultTitle'] ?? 'Best Cool Electronics'))
@section('description', $homePage?->meta_description ?: ($siteSettings['seo.defaultDescription'] ?? ''))
@if($homePage?->og_image) @section('og_image', filter_var($homePage->og_image, FILTER_VALIDATE_URL) ? $homePage->og_image : asset(ltrim($homePage->og_image, '/'))) @endif

@section('content')
@php
    $assetUrl = fn ($path) => filter_var((string) $path, FILTER_VALIDATE_URL) ? $path : asset(ltrim((string) $path, '/'));
    $categoryBlocks = $displayBlocks->where('block.type', 'FEATURED_CATEGORIES');
    $hasCategoryBlock = $categoryBlocks->isNotEmpty();
    $serviceTiles = $siteSettings['home.services'] ?? [];
@endphp

@if($banners->isNotEmpty())
    <section class="site-width py-5 sm:py-6" aria-label="Featured promotions">
        <div class="relative overflow-hidden rounded-xl bg-zinc-100" data-banner-slider>
            @foreach($banners as $index => $banner)
                @php($content = $banner->content ?? [])
                <a href="{{ $content['link'] ?? route('shop.index') }}" class="block {{ $index === 0 ? '' : 'hidden' }}" data-banner-slide aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                    <img src="{{ $assetUrl($content['image']) }}" alt="{{ $content['alt'] ?? 'Best Cool Electronics promotion' }}" class="max-h-[430px] w-full object-cover" width="1600" height="480" @if($index === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                </a>
            @endforeach
            @if($banners->count() > 1)
                <div class="absolute inset-x-0 bottom-3 flex items-center justify-center gap-2">
                    @foreach($banners as $index => $banner)<button type="button" data-banner-dot="{{ $index }}" aria-label="Show promotion {{ $index + 1 }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}" class="size-2.5 rounded-full border border-white bg-white/60 shadow aria-[current=true]:bg-brand"></button>@endforeach
                </div>
                <button type="button" data-banner-prev class="absolute left-3 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl shadow" aria-label="Previous promotion">‹</button>
                <button type="button" data-banner-next class="absolute right-3 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl shadow" aria-label="Next promotion">›</button>
            @endif
        </div>
    </section>
@endif

<div class="page-bg text-ink">
    <div class="site-width pt-5">
        @if($serviceTiles)
            <nav class="grid grid-cols-2 gap-2.5 lg:grid-cols-4" aria-label="Shopping services">
                @foreach($serviceTiles as $tile)
                    <a href="{{ $tile['href'] ?? route('shop.index') }}" class="home-service">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand text-xl text-white" aria-hidden="true">{{ $tile['icon'] ?? '•' }}</span>
                        <span class="min-w-0"><strong class="block text-[13px] leading-5">{{ $tile['title'] ?? '' }}</strong><small class="block text-[10px] leading-4 text-zinc-500">{{ $tile['text'] ?? '' }}</small></span>
                        <span class="ml-auto hidden text-brand xl:block" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </nav>
        @endif

        @unless($hasCategoryBlock)
            @include('store.partials.home-categories', ['categories' => $siteCategories, 'assetUrl' => $assetUrl, 'heading' => 'Featured Category'])
        @endunless

        @foreach($displayBlocks as $entry)
            @php($block = $entry['block'])
            @php($content = $block->content ?? [])
            @switch($block->type)
                @case('FEATURED_CATEGORIES')
                    @include('store.partials.home-categories', ['categories' => $entry['categories'], 'assetUrl' => $assetUrl, 'heading' => $content['heading'] ?? 'Featured Category', 'subheading' => $content['subheading'] ?? 'Discover your everyday essentials, all in one place.'])
                    @break
                @case('FEATURED_PRODUCTS')
                @case('CATEGORY_PRODUCTS')
                    @if($entry['products']->isNotEmpty())
                        <section class="mb-5 mt-7 bg-white px-4 py-6 sm:px-5" aria-labelledby="products-{{ $block->id }}">
                            <div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="products-{{ $block->id }}" class="section-heading">{{ $content['heading'] ?? 'Featured Products' }}</h2>@if(!empty($content['subheading']))<p class="mt-1 text-xs text-zinc-500">{{ $content['subheading'] }}</p>@endif</div><a href="{{ $content['url'] ?? route('shop.index') }}" class="shrink-0 text-xs font-bold text-brand hover:text-brand-dark">View all →</a></div>
                            <div class="grid grid-cols-2 gap-1.5 sm:gap-2 md:grid-cols-4 xl:grid-cols-5">@foreach($entry['products'] as $product)@include('store.partials.product-card', ['product' => $product])@endforeach</div>
                        </section>
                    @endif
                    @break
                @case('RICH_TEXT')
                    <section class="site-width max-w-4xl py-8">@if(!empty($content['heading']))<h2 class="section-heading mb-2">{{ $content['heading'] }}</h2>@endif @if(!empty($content['subheading']))<p class="mb-4 text-sm text-zinc-500">{{ $content['subheading'] }}</p>@endif<article class="content-rich">{!! \App\Support\SafeHtml::clean($content['html'] ?? '') !!}</article></section>
                    @break
                @case('IMAGE_TEXT')
                    <section class="site-width grid items-center gap-6 py-8 md:grid-cols-2">@if(!empty($content['image']))<img src="{{ $assetUrl($content['image']) }}" alt="{{ $content['alt'] ?? $content['heading'] ?? '' }}" class="aspect-[4/3] w-full rounded-xl object-cover {{ ($content['layout'] ?? '') === 'right' ? 'md:order-2' : '' }}" loading="lazy" decoding="async">@endif<div><h2 class="section-heading mb-3">{{ $content['heading'] ?? '' }}</h2><div class="content-rich">{!! \App\Support\SafeHtml::clean($content['html'] ?? '') !!}</div>@if(!empty($content['link']) && !empty($content['linkLabel']))<a href="{{ $content['link'] }}" class="mt-4 inline-flex font-bold text-brand">{{ $content['linkLabel'] }} →</a>@endif</div></section>
                    @break
                @case('IMAGE_GALLERY')
                    <section class="site-width py-8"><h2 class="section-heading mb-4">{{ $content['heading'] ?? '' }}</h2><div class="grid grid-cols-2 gap-3 md:grid-cols-4">@foreach(($content['images'] ?? []) as $image)<img src="{{ $assetUrl($image['url'] ?? '') }}" alt="{{ $image['alt'] ?? '' }}" class="aspect-[4/3] w-full rounded-lg object-cover" loading="lazy" decoding="async">@endforeach</div></section>
                    @break
                @case('FAQ')
                    <section class="site-width max-w-3xl py-8"><h2 class="section-heading mb-4">{{ $content['heading'] ?? 'Frequently Asked Questions' }}</h2><div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white">@foreach(($content['items'] ?? []) as $item)<details class="group p-4"><summary class="cursor-pointer font-semibold">{{ $item['question'] ?? '' }}</summary><div class="content-rich pt-3">{!! \App\Support\SafeHtml::clean($item['answer'] ?? '') !!}</div></details>@endforeach</div></section>
                    @break
                @case('TESTIMONIALS')
                    @if(!empty($content['items']))<section class="bg-white py-9"><div class="site-width"><h2 class="section-heading mb-5">{{ $content['heading'] ?? 'What Our Customers Say' }}</h2><div class="grid gap-4 md:grid-cols-3">@foreach($content['items'] as $item)<figure class="rounded-lg border border-zinc-200 p-5"><div class="text-amber-500" aria-label="{{ $item['rating'] ?? 5 }} out of 5 stars">{{ str_repeat('★', max(1, min(5, (int)($item['rating'] ?? 5)))) }}</div><blockquote class="mt-3 text-sm leading-6 text-zinc-600">“{{ $item['text'] ?? '' }}”</blockquote><figcaption class="mt-4 text-sm font-semibold">{{ $item['name'] ?? '' }} <span class="ml-1 font-normal text-zinc-400">{{ $item['location'] ?? '' }}</span></figcaption></figure>@endforeach</div></div></section>@endif
                    @break
                @case('CONTACT_INFO')
                    <section class="site-width py-8"><h2 class="section-heading mb-4">{{ $content['heading'] ?? 'Get in Touch' }}</h2><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach(['Phone' => ['value' => $content['phone'] ?? '', 'href' => !empty($content['phone']) ? 'tel:'.$content['phone'] : ''], 'Email' => ['value' => $content['email'] ?? '', 'href' => !empty($content['email']) ? 'mailto:'.$content['email'] : ''], 'Address' => ['value' => $content['address'] ?? '', 'href' => ''], 'Hours' => ['value' => $content['hours'] ?? '', 'href' => '']] as $label => $item)@if($item['value'])<div class="rounded-lg border bg-white p-5 text-center"><div class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ $label }}</div>@if($item['href'])<a href="{{ $item['href'] }}" class="mt-1 block text-sm font-medium">{{ $item['value'] }}</a>@else<div class="mt-1 text-sm font-medium">{{ $item['value'] }}</div>@endif</div>@endif @endforeach</div></section>
                    @break
                @case('MAP_EMBED')
                    @if(!empty($content['embedUrl']) && str_starts_with($content['embedUrl'], 'https://www.google.com/maps/embed?'))<section class="site-width py-8"><h2 class="section-heading mb-4">{{ $content['heading'] ?? 'Find Us' }}</h2><iframe src="{{ $content['embedUrl'] }}" title="{{ $content['heading'] ?? 'Map' }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="h-80 w-full rounded-lg border"></iframe></section>@endif
                    @break
            @endswitch
        @endforeach

        @if(!empty($siteSettings['home.about.html']))
            <section class="py-9"><span class="text-[9px] font-bold uppercase tracking-[.18em] text-brand">{{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }}</span><h2 class="mt-2 text-xl font-extrabold tracking-tight sm:text-2xl">{{ $siteSettings['home.about.heading'] ?? 'Everyday essentials. A better place to call home.' }}</h2><article class="content-rich mt-3 max-w-5xl text-xs leading-6 text-zinc-600">{!! \App\Support\SafeHtml::clean($siteSettings['home.about.html']) !!}</article></section>
        @endif
    </div>
</div>
@endsection
