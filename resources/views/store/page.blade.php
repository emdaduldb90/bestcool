@extends('layouts.store')
@section('title', $page->meta_title ?: $page->title)
@section('description', $page->meta_description ?: '')
@if($page->og_image) @section('og_image', filter_var($page->og_image, FILTER_VALIDATE_URL) ? $page->og_image : url(ltrim($page->og_image, '/'))) @endif

@section('content')
@php($assetUrl = fn ($path) => filter_var($path, FILTER_VALIDATE_URL) ? $path : asset(ltrim($path, '/')))
@foreach($displayBlocks as $entry)
    @php($block = $entry['block'])
    @php($content = $block->content ?? [])
    @switch($block->type)
        @case('BANNER')
            @if(!empty($content['image']))<section class="site-width py-6"><a href="{{ $content['link'] ?? '#' }}" class="block overflow-hidden rounded-xl"><img src="{{ $assetUrl($content['image']) }}" alt="{{ $content['alt'] ?? $page->title }}" class="h-auto w-full object-cover" loading="lazy" decoding="async"></a></section>@endif
            @break
        @case('FEATURED_CATEGORIES')
            @include('store.partials.home-categories', ['categories' => $entry['categories'], 'assetUrl' => $assetUrl, 'heading' => $content['heading'] ?? 'Featured Category', 'subheading' => $content['subheading'] ?? ''])
            @break
        @case('FEATURED_PRODUCTS')
        @case('CATEGORY_PRODUCTS')
            @if($entry['products']->isNotEmpty())<section class="site-width py-8"><div class="mb-4 flex items-end justify-between"><div><h2 class="section-heading">{{ $content['heading'] ?? 'Featured Products' }}</h2>@if(!empty($content['subheading']))<p class="mt-1 text-xs text-zinc-500">{{ $content['subheading'] }}</p>@endif</div><a href="{{ $content['url'] ?? route('shop.index') }}" class="text-sm font-bold text-brand">View all →</a></div><div class="grid grid-cols-2 gap-2 md:grid-cols-4">@foreach($entry['products'] as $product)@include('store.partials.product-card', ['product' => $product])@endforeach</div></section>@endif
            @break
        @case('RICH_TEXT')
            <section class="site-width max-w-3xl py-8">@if(!empty($content['heading']))<h2 class="section-heading mb-2">{{ $content['heading'] }}</h2>@endif @if(!empty($content['subheading']))<p class="mb-4 text-sm text-zinc-500">{{ $content['subheading'] }}</p>@endif<article class="content-rich">{!! \App\Support\SafeHtml::clean($content['html'] ?? '') !!}</article></section>
            @break
        @case('IMAGE_TEXT')
            <section class="site-width grid items-center gap-8 py-8 md:grid-cols-2">
                @if(!empty($content['image']))<img src="{{ $assetUrl($content['image']) }}" alt="{{ $content['heading'] ?? $page->title }}" class="{{ ($content['layout'] ?? '') === 'right' ? 'md:order-2' : '' }} aspect-[4/3] w-full rounded-xl object-cover" loading="lazy" decoding="async">@endif
                <div>@if(!empty($content['heading']))<h2 class="mb-4 text-2xl font-extrabold">{{ $content['heading'] }}</h2>@endif<div class="content-rich">{!! \App\Support\SafeHtml::clean($content['html'] ?? '') !!}</div></div>
            </section>
            @break
        @case('IMAGE_GALLERY')
            <section class="site-width py-8"><h2 class="section-heading mb-5">{{ $content['heading'] ?? '' }}</h2><div class="grid grid-cols-2 gap-3 md:grid-cols-4">@foreach(($content['images'] ?? []) as $image)<img src="{{ $assetUrl($image['url']) }}" alt="{{ $image['alt'] ?? '' }}" class="aspect-[4/3] w-full rounded-lg object-cover" loading="lazy" decoding="async">@endforeach</div></section>
            @break
        @case('CONTACT_INFO')
            <section class="site-width py-9"><h2 class="section-heading mb-5">{{ $content['heading'] ?? 'Get in Touch' }}</h2><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach(['Phone' => ['☎', $content['phone'] ?? '', 'tel:'], 'Email' => ['✉', $content['email'] ?? '', 'mailto:'], 'Address' => ['⌖', $content['address'] ?? '', ''], 'Hours' => ['◷', $content['hours'] ?? '', '']] as $label => [$icon,$value,$scheme]) @if($value)<div class="rounded-lg border border-zinc-200 bg-white p-5 text-center"><div class="mx-auto mb-3 grid size-11 place-items-center rounded-full bg-brand-soft text-xl text-brand">{{ $icon }}</div><div class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ $label }}</div>@if($scheme)<a href="{{ $scheme }}{{ $value }}" class="mt-1 block text-sm font-medium">{{ $value }}</a>@else<div class="mt-1 text-sm font-medium">{{ $value }}</div>@endif</div>@endif @endforeach</div></section>
            @break
        @case('MAP_EMBED')
            @if(!empty($content['embedUrl']) && str_starts_with($content['embedUrl'], 'https://www.google.com/maps/embed?'))<section class="site-width py-8"><h2 class="section-heading mb-4">{{ $content['heading'] ?? 'Find Us' }}</h2><iframe src="{{ $content['embedUrl'] }}" title="{{ $content['heading'] ?? 'Map' }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="h-80 w-full rounded-lg border border-zinc-200"></iframe></section>@endif
            @break
        @case('FAQ')
            <section class="site-width max-w-3xl py-9"><h2 class="section-heading mb-5">{{ $content['heading'] ?? 'Frequently Asked Questions' }}</h2><div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white">@foreach(($content['items'] ?? []) as $item)<details class="group p-4"><summary class="cursor-pointer list-none font-semibold">{{ $item['question'] ?? '' }}<span class="float-right text-brand">+</span></summary><div class="content-rich pt-3">{!! \App\Support\SafeHtml::clean($item['answer'] ?? '') !!}</div></details>@endforeach</div></section>
            @break
        @case('TESTIMONIALS')
            <section class="site-width py-8"><h2 class="section-heading mb-5">{{ $content['heading'] ?? 'What Our Customers Say' }}</h2><div class="grid gap-4 md:grid-cols-3">@foreach(($content['items'] ?? []) as $item)<blockquote class="rounded-lg border border-zinc-200 p-5 text-sm leading-6 text-zinc-600">“{{ $item['text'] ?? '' }}”<footer class="mt-4 font-semibold text-ink">{{ $item['name'] ?? '' }} · {{ $item['location'] ?? '' }}</footer></blockquote>@endforeach</div></section>
            @break
    @endswitch
@endforeach
@if($page->slug === 'contact-us')
<section class="site-width max-w-2xl py-8"><h2 class="section-heading">Send us a message</h2><form method="POST" action="{{ route('inquiries.store') }}" class="mt-5 grid gap-3 rounded-xl border border-zinc-200 bg-white p-5">@csrf
    <div class="absolute -left-[10000px]" aria-hidden="true"><label>Leave empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <label class="text-sm font-semibold">Name<input name="name" value="{{ old('name') }}" required maxlength="120" class="admin-input mt-1 w-full"></label><div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold">Email<input type="email" name="email" value="{{ old('email') }}" maxlength="190" class="admin-input mt-1 w-full"></label><label class="text-sm font-semibold">Phone<input name="phone" value="{{ old('phone') }}" maxlength="64" class="admin-input mt-1 w-full"></label></div><label class="text-sm font-semibold">Subject<input name="subject" value="{{ old('subject') }}" required maxlength="190" class="admin-input mt-1 w-full"></label><label class="text-sm font-semibold">Message<textarea name="message" required minlength="10" maxlength="5000" rows="5" class="admin-input mt-1 w-full">{{ old('message') }}</textarea></label><button class="rounded-lg bg-brand px-5 py-3 font-bold text-white">Send message</button>
</form></section>
@endif
@endsection
