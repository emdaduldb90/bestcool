@extends('admin.layout')
@php($editing = $product->exists)
@section('title', $editing ? 'Edit product' : 'Add product')
@section('page-title', $editing ? 'Edit product' : 'Add product')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" class="space-y-6">
    @csrf @if($editing) @method('PUT') @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="mb-5 text-base font-bold">Product details</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold">Name<input name="name" required value="{{ old('name', $product->name) }}" class="admin-input mt-1.5"></label>
            <label class="text-sm font-semibold">Slug<input name="slug" value="{{ old('slug', $product->slug) }}" placeholder="Auto-generated from product name" class="admin-input mt-1.5"></label>
            <label class="text-sm font-semibold">SKU<input name="sku" value="{{ old('sku', $product->sku) }}" class="admin-input mt-1.5"></label>
            <label class="text-sm font-semibold">Brand<input name="brand" value="{{ old('brand', $product->brand) }}" class="admin-input mt-1.5"></label>
            <label class="text-sm font-semibold">Category<select name="category_id" required class="admin-input mt-1.5"><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <div class="grid grid-cols-2 gap-3"><label class="text-sm font-semibold">Regular price<input name="regular_price" type="number" min="0" step="0.01" required value="{{ old('regular_price', $product->regular_price) }}" class="admin-input mt-1.5"></label><label class="text-sm font-semibold">Sale price<input name="sale_price" type="number" min="0" step="0.01" value="{{ old('sale_price', $product->sale_price) }}" class="admin-input mt-1.5"></label></div>
            <div class="grid grid-cols-2 gap-3">@if(!$editing)<label class="text-sm font-semibold">Initial stock<input name="stock" type="number" min="0" required value="{{ old('stock', 0) }}" class="admin-input mt-1.5"></label>@else<div class="text-sm font-semibold">Current stock <p class="mt-2 text-2xl font-extrabold">{{ $product->stock }} <a href="{{ route('admin.inventory.index', ['search' => $product->sku ?: $product->name]) }}" class="ml-2 text-xs font-semibold text-sky-700">Adjust stock →</a></p></div>@endif<label class="text-sm font-semibold">Low-stock alert<input name="low_stock_threshold" type="number" min="0" required value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" class="admin-input mt-1.5"></label></div>
        </div>
        <label class="mt-4 block text-sm font-semibold">Description<textarea name="description" rows="6" class="admin-input mt-1.5">{{ old('description', $product->description) }}</textarea></label>
        <label class="mt-4 block text-sm font-semibold">Specifications<textarea name="specifications" rows="5" class="admin-input mt-1.5">{{ old('specifications', $product->specifications) }}</textarea></label>
        <div class="mt-6 border-t border-slate-100 pt-5"><h3 class="font-bold">Product FAQs</h3><p class="mt-1 text-xs text-slate-500">Questions and answers appear on the storefront product page.</p>
            @php($questionRows = old('questions', $product->questions ?? []))
            <div data-faq-list class="mt-3 space-y-3">
                @for($i = 0; $i < max(3, count($questionRows)); $i++)
                    <div data-faq-row class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2"><label class="text-xs font-semibold">Question<input name="questions[{{ $i }}][question]" value="{{ $questionRows[$i]['question'] ?? '' }}" class="admin-input mt-1"></label><div><label class="text-xs font-semibold">Answer<textarea name="questions[{{ $i }}][answer]" rows="2" class="admin-input mt-1">{{ $questionRows[$i]['answer'] ?? '' }}</textarea></label><button type="button" data-remove-faq class="mt-1 text-xs font-semibold text-rose-700">Remove question</button></div></div>
                @endfor
            </div>
            <button type="button" data-add-faq data-next-index="{{ max(3, count($questionRows)) }}" class="mt-3 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold">Add question</button>
            <template data-faq-template><div data-faq-row class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2"><label class="text-xs font-semibold">Question<input name="questions[__INDEX__][question]" class="admin-input mt-1"></label><div><label class="text-xs font-semibold">Answer<textarea name="questions[__INDEX__][answer]" rows="2" class="admin-input mt-1"></textarea></label><button type="button" data-remove-faq class="mt-1 text-xs font-semibold text-rose-700">Remove question</button></div></div></template>
        </div>
        <div class="mt-6 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2"><div><label class="block text-sm font-semibold">SEO title<input name="meta_title" maxlength="190" value="{{ old('meta_title', $product->meta_title) }}" class="admin-input mt-1.5 w-full"></label><label class="mt-4 block text-sm font-semibold">SEO description<textarea name="meta_description" maxlength="500" rows="2" class="admin-input mt-1.5 w-full">{{ old('meta_description', $product->meta_description) }}</textarea></label></div><div><p class="text-sm font-semibold">Discount preview</p><output data-discount-preview class="mt-2 block rounded-xl bg-sky-50 p-4 text-sm text-sky-900">Enter regular and sale prices to preview the discount.</output></div></div>
        <label class="mt-4 inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> Visible in the storefront</label>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-base font-bold">Product images</h2><p class="mt-1 text-sm text-slate-500">Keep existing images, add image URLs, or upload JPG, PNG, WebP or AVIF files.</p>
        @if($editing && $product->images->isNotEmpty())<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">@foreach($product->images as $index => $image)<div class="rounded-xl border border-slate-200 p-2 text-xs"><img src="{{ filter_var($image->url, FILTER_VALIDATE_URL) ? $image->url : asset(ltrim($image->url, '/')) }}" alt="{{ $image->alt }}" class="h-28 w-full rounded-lg object-contain"><label class="mt-2 block">Alt text<input name="image_alt[{{ $image->id }}]" value="{{ $image->alt }}" maxlength="190" class="admin-input mt-1 py-1"></label><label class="mt-2 flex items-center gap-2"><input type="radio" name="primary_image_id" value="{{ $image->id }}" @checked($image->is_primary || (!$product->images->contains(fn ($candidate) => $candidate->is_primary) && $index === 0))> Primary image</label><label class="mt-2 block">Display order<input type="number" min="0" name="image_order[{{ $image->id }}]" value="{{ $image->sort_order }}" class="admin-input mt-1 py-1"></label><label class="mt-2 flex items-center gap-2 text-rose-700"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}"> Remove</label></div>@endforeach</div>@endif
        <div class="mt-5 grid gap-3 sm:grid-cols-2">@for($i = 0; $i < 4; $i++)<label class="text-sm font-semibold">Add image URL {{ $i + 1 }}<input name="image_urls[]" value="{{ old('image_urls.'.$i) }}" placeholder="/uploads/image.webp or https://…" class="admin-input mt-1.5"></label>@endfor</div>
        <label class="mt-4 block text-sm font-semibold">Upload images<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/avif" multiple class="mt-1.5 block w-full rounded-xl border border-slate-300 p-2 text-sm"></label>
    </section>
    <div class="flex flex-wrap gap-3"><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-700">{{ $editing ? 'Save changes' : 'Create product' }}</button><a href="{{ route('admin.products.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold">Cancel</a></div>
</form>
@endsection
