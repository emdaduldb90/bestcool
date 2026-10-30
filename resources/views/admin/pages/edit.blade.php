@extends('admin.layout')
@section('page-title', 'Edit '.$page->title)
@section('content')
<div class="mb-5"><a href="{{ route('admin.pages.index') }}" class="text-sm font-semibold text-sky-700">← Pages</a></div>
<form method="POST" action="{{ route('admin.pages.update', $page) }}" class="mb-8 rounded-xl border border-slate-200 bg-white p-5">@csrf @method('PUT')
    <h2 class="mb-4 text-lg font-bold">Page information & SEO</h2><div class="grid gap-4 md:grid-cols-2">
        <label class="text-sm font-semibold">Title<input name="title" value="{{ old('title', $page->title) }}" class="admin-input mt-1 w-full" required></label>
        <label class="text-sm font-semibold">Allowed page slug<input name="slug" value="{{ old('slug', $page->slug) }}" class="admin-input mt-1 w-full" required><small class="font-normal text-slate-500">Reserved storefront paths are blocked.</small></label>
        <label class="text-sm font-semibold">Meta title<input name="meta_title" value="{{ old('meta_title', $page->meta_title) }}" class="admin-input mt-1 w-full" maxlength="190"></label>
        <label class="text-sm font-semibold">Meta description<textarea name="meta_description" rows="2" class="admin-input mt-1 w-full">{{ old('meta_description', $page->meta_description) }}</textarea></label>
        <label class="text-sm font-semibold md:col-span-2">Open Graph image (HTTPS URL or /uploads/ path)<input name="og_image" value="{{ old('og_image', $page->og_image) }}" class="admin-input mt-1 w-full"></label>
    </div><button class="mt-4 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Save page details</button>
</form>

<div class="mb-4 flex items-end justify-between"><div><h2 class="text-xl font-extrabold">Page sections</h2><p class="text-sm text-slate-500">Use the accessible up/down controls to change storefront order.</p></div><span class="text-xs text-slate-400">{{ $page->blocks->count() }} sections</span></div>
<div class="space-y-4">
@foreach($page->blocks as $block)
    @php($data = $block->content ?? [])
    <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><span class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">{{ str_replace('_', ' ', $block->type) }}</span><span class="ml-2 text-xs text-slate-500">Order {{ $block->sort_order }}</span></div><div class="flex gap-2">
            <form method="POST" action="{{ route('admin.pages.blocks.move', $block) }}">@csrf<button name="direction" value="up" class="rounded border px-2 py-1 text-xs" aria-label="Move section up">↑</button><button name="direction" value="down" class="rounded border px-2 py-1 text-xs" aria-label="Move section down">↓</button></form>
            <form method="POST" action="{{ route('admin.pages.blocks.destroy', $block) }}" data-confirm="Delete this page section?">@csrf @method('DELETE')<button class="rounded border border-rose-200 px-2 py-1 text-xs font-bold text-rose-700">Delete</button></form>
        </div></div>
        <form method="POST" action="{{ route('admin.pages.blocks.update', $block) }}">@csrf @method('PATCH')
            <div class="mb-4"><label class="inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_enabled" value="1" @checked($block->is_enabled)> Visible on storefront</label></div>
            @include('admin.pages.block-fields', ['type' => $block->type, 'data' => $data, 'categories' => $categories, 'products' => $products, 'prefix' => 'content'])
            <button class="mt-4 rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white">Save section</button>
        </form>
    </section>
@endforeach
</div>

<section class="mt-8 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:p-5"><h2 class="text-lg font-bold">Add a section</h2><form method="POST" action="{{ route('admin.pages.blocks.store', $page) }}" class="mt-4">@csrf
    <label class="mb-4 block text-sm font-semibold">Section type<select name="type" data-block-type class="admin-input mt-1 w-full md:max-w-sm" required><option value="">Choose a section type</option>@foreach(['BANNER','FEATURED_CATEGORIES','FEATURED_PRODUCTS','CATEGORY_PRODUCTS','TESTIMONIALS','RICH_TEXT','IMAGE_TEXT','IMAGE_GALLERY','FAQ','CONTACT_INFO','MAP_EMBED'] as $type)<option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>@endforeach</select></label>
    @foreach(['BANNER','FEATURED_CATEGORIES','FEATURED_PRODUCTS','CATEGORY_PRODUCTS','TESTIMONIALS','RICH_TEXT','IMAGE_TEXT','IMAGE_GALLERY','FAQ','CONTACT_INFO','MAP_EMBED'] as $type)<fieldset data-block-fields="{{ $type }}" class="mb-4 hidden rounded-lg border border-sky-200 bg-white p-4"><legend class="px-1 text-xs font-bold text-slate-500">{{ str_replace('_', ' ', $type) }}</legend>@include('admin.pages.block-fields', ['type' => $type, 'data' => [], 'categories' => $categories, 'products' => $products, 'prefix' => 'content'])</fieldset>@endforeach
    <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Add section</button>
</form></section>
@endsection
