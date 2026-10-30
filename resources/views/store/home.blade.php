@extends('layouts.store')

@section('title', $siteSettings['seo.defaultTitle'] ?? 'Best Cool Electronics')
@section('description', $siteSettings['seo.defaultDescription'] ?? '')

@section('content')
@php($assetUrl = fn ($path) => filter_var($path, FILTER_VALIDATE_URL) ? $path : asset(ltrim($path, '/')))

@if($hero)
    <section class="site-width py-6">
        <a href="{{ $hero->content['link'] ?? route('shop.index') }}" class="block overflow-hidden rounded-xl bg-zinc-100">
            <img src="{{ $assetUrl($hero->content['image']) }}" alt="{{ $hero->content['alt'] ?? 'Best Cool Electronics' }}" class="h-auto w-full object-cover" width="1600" height="400" fetchpriority="high" decoding="async">
        </a>
    </section>
@endif

<div class="page-bg text-ink">
    <div class="site-width pt-7">
        <nav class="grid grid-cols-2 gap-2.5 lg:grid-cols-4" aria-label="Shopping services">
            @foreach([
                ['⌕', 'Product Finder', 'Find the right fit for your home', route('shop.index')],
                ['☎', 'Talk to an Expert', 'A little help choosing better', 'tel:'.($siteSettings['contact.phone'] ?? '')],
                ['❄', 'AC Collection', 'Comfort for every space', route('shop.index', ['category' => 'air-conditioner'])],
                ['◉', 'Customer Support', 'We are here after your purchase', route('page.show', 'contact-us')],
            ] as [$icon, $title, $text, $href])
                <a href="{{ $href }}" class="home-service">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand text-xl text-white">{{ $icon }}</span>
                    <span class="min-w-0"><strong class="block text-[13px] leading-5">{{ $title }}</strong><small class="block text-[10px] leading-4 text-zinc-500">{{ $text }}</small></span>
                    <span class="ml-auto hidden text-brand xl:block">→</span>
                </a>
            @endforeach
        </nav>

        <section class="py-9" aria-labelledby="featured-categories">
            <div class="mb-5 text-center">
                <h2 id="featured-categories" class="section-heading">Featured Category</h2>
                <p class="mt-1.5 text-xs text-zinc-500">Discover your everyday essentials, all in one place.</p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach($siteCategories as $category)
                    <a href="{{ route('shop.index', ['category' => $category->slug]) }}" class="group flex min-h-28 flex-col items-center justify-center gap-2.5 rounded bg-white px-2 py-4 text-center transition hover:-translate-y-0.5 hover:text-brand">
                        @if($category->image)
                            <span class="grid size-14 place-items-center overflow-hidden rounded-full bg-brand-soft"><img src="{{ $assetUrl($category->image) }}" alt="" class="size-full object-cover" loading="lazy" decoding="async" width="64" height="64"></span>
                        @else
                            <span class="grid size-14 place-items-center rounded-full bg-brand-soft text-2xl text-brand">⌂</span>
                        @endif
                        <span class="text-xs font-semibold">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        @foreach($sections as $section)
            @php($blockContent = $section['block']->content ?? [])
            <section class="mb-5 bg-white px-4 py-6 sm:px-5" aria-labelledby="products-{{ $section['block']->id }}">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div><h2 id="products-{{ $section['block']->id }}" class="section-heading">{{ $blockContent['heading'] ?? 'Featured Products' }}</h2>@if(!empty($blockContent['subheading']))<p class="mt-1 text-xs text-zinc-500">{{ $blockContent['subheading'] }}</p>@endif</div>
                    <a href="{{ $blockContent['url'] ?? route('shop.index') }}" class="shrink-0 text-xs font-bold text-brand hover:text-brand-dark">View all →</a>
                </div>
                <div class="grid grid-cols-2 gap-1.5 sm:gap-2 md:grid-cols-4 xl:grid-cols-5">
                    @foreach($section['products'] as $product)
                        @include('store.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="py-9" aria-labelledby="home-about">
            <span class="text-[9px] font-bold uppercase tracking-[.18em] text-brand">Welcome to Best Cool Electronics</span>
            <h2 id="home-about" class="mt-2 text-xl font-extrabold tracking-tight sm:text-2xl">Everyday essentials. A better place to call home.</h2>
            <p class="mt-3 max-w-5xl text-xs leading-6 text-zinc-600">Explore air conditioners, kitchen appliances and home electronics from the brands you know. Compare your options, discover useful upgrades and choose what works for your home, your family and your budget.</p>
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                <div><h3 class="mb-2 text-sm font-bold">Comfort, for every season</h3><p class="text-xs leading-6 text-zinc-600">Find cooling solutions for your space, from compact rooms to busy workplaces. Browse our <a class="text-brand underline" href="{{ route('shop.index', ['category' => 'air-conditioner']) }}">air conditioner collection</a> and get in touch for help choosing.</p></div>
                <div><h3 class="mb-2 text-sm font-bold">Make more of the everyday</h3><p class="text-xs leading-6 text-zinc-600">From your morning coffee to family dinners, discover practical kitchen and home appliances. <a class="text-brand underline" href="{{ route('shop.index') }}">Explore the collection</a> and find your next everyday favourite.</p></div>
                <div><h3 class="mb-2 text-sm font-bold">A team you can talk to</h3><p class="text-xs leading-6 text-zinc-600">Have a question about a product or your order? <a class="text-brand underline" href="{{ route('page.show', 'contact-us') }}">Contact us</a> or <a class="text-brand underline" href="{{ route('page.show', 'showroom') }}">visit our showroom</a>.</p></div>
            </div>
        </section>
    </div>

    @if($testimonials && !empty($testimonials->content['items']))
        <section class="bg-white py-10">
            <div class="site-width">
                <h2 class="section-heading mb-5">{{ $testimonials->content['heading'] ?? 'What Our Customers Say' }}</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($testimonials->content['items'] as $item)
                        <figure class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="text-amber-500" aria-label="{{ $item['rating'] ?? 5 }} out of 5 stars">{{ str_repeat('★', max(1, min(5, (int)($item['rating'] ?? 5)))) }}</div>
                            <blockquote class="mt-3 text-sm leading-6 text-zinc-600">“{{ $item['text'] ?? '' }}”</blockquote>
                            <figcaption class="mt-4 text-sm font-semibold">{{ $item['name'] ?? '' }} <span class="ml-1 font-normal text-zinc-400">{{ $item['location'] ?? '' }}</span></figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
