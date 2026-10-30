@extends('layouts.store')
@section('title', $page->meta_title ?: $page->title)
@section('description', $page->meta_description ?: '')

@section('content')
@php($assetUrl = fn ($path) => filter_var($path, FILTER_VALIDATE_URL) ? $path : asset(ltrim($path, '/')))
@foreach($page->blocks as $block)
    @php($content = $block->content ?? [])
    @switch($block->type)
        @case('BANNER')
            @if(!empty($content['image']))<section class="site-width py-6"><a href="{{ $content['link'] ?? '#' }}" class="block overflow-hidden rounded-xl"><img src="{{ $assetUrl($content['image']) }}" alt="{{ $content['alt'] ?? $page->title }}" class="h-auto w-full object-cover" loading="lazy" decoding="async"></a></section>@endif
            @break
        @case('RICH_TEXT')
            <section class="site-width max-w-3xl py-8"><article class="content-rich">{!! \App\Support\SafeHtml::clean($content['html'] ?? '') !!}</article></section>
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
            @if(!empty($content['embedUrl']))<section class="site-width py-8"><h2 class="section-heading mb-4">{{ $content['heading'] ?? 'Find Us' }}</h2><iframe src="{{ $content['embedUrl'] }}" title="{{ $content['heading'] ?? 'Map' }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="h-80 w-full rounded-lg border border-zinc-200"></iframe></section>@endif
            @break
        @case('FAQ')
            <section class="site-width max-w-3xl py-9"><h2 class="section-heading mb-5">{{ $content['heading'] ?? 'Frequently Asked Questions' }}</h2><div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white">@foreach(($content['items'] ?? []) as $item)<details class="group p-4"><summary class="cursor-pointer list-none font-semibold">{{ $item['question'] ?? '' }}<span class="float-right text-brand">+</span></summary><div class="content-rich pt-3">{!! \App\Support\SafeHtml::clean($item['answer'] ?? '') !!}</div></details>@endforeach</div></section>
            @break
        @case('TESTIMONIALS')
            <section class="site-width py-8"><h2 class="section-heading mb-5">{{ $content['heading'] ?? 'What Our Customers Say' }}</h2><div class="grid gap-4 md:grid-cols-3">@foreach(($content['items'] ?? []) as $item)<blockquote class="rounded-lg border border-zinc-200 p-5 text-sm leading-6 text-zinc-600">“{{ $item['text'] ?? '' }}”<footer class="mt-4 font-semibold text-ink">{{ $item['name'] ?? '' }} · {{ $item['location'] ?? '' }}</footer></blockquote>@endforeach</div></section>
            @break
    @endswitch
@endforeach
@endsection
