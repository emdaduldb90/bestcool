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
            <div class="grid grid-cols-2 gap-3"><label class="text-sm font-semibold">Stock<input name="stock" type="number" min="0" required value="{{ old('stock', $product->stock ?? 0) }}" class="admin-input mt-1.5"></label><label class="text-sm font-semibold">Low-stock alert<input name="low_stock_threshold" type="number" min="0" required value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" class="admin-input mt-1.5"></label></div>
        </div>
        <label class="mt-4 block text-sm font-semibold">Description<textarea name="description" rows="6" class="admin-input mt-1.5">{{ old('description', $product->description) }}</textarea></label>
        <label class="mt-4 block text-sm font-semibold">Specifications<textarea name="specifications" rows="5" class="admin-input mt-1.5">{{ old('specifications', $product->specifications) }}</textarea></label>
        <label class="mt-4 inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> Visible in the storefront</label>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-base font-bold">Product images</h2><p class="mt-1 text-sm text-slate-500">Keep existing images, add image URLs, or upload JPG, PNG, WebP or AVIF files.</p>
        @if($editing && $product->images->isNotEmpty())<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach($product->images as $image)<label class="rounded-xl border border-slate-200 p-2 text-xs"><img src="{{ filter_var($image->url, FILTER_VALIDATE_URL) ? $image->url : asset(ltrim($image->url, '/')) }}" alt="" class="h-28 w-full rounded-lg object-contain"><span class="mt-2 flex items-center gap-2"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}"> Remove</span></label>@endforeach</div>@endif
        <div class="mt-5 grid gap-3 sm:grid-cols-2">@for($i = 0; $i < 4; $i++)<label class="text-sm font-semibold">Add image URL {{ $i + 1 }}<input name="image_urls[]" value="{{ old('image_urls.'.$i) }}" placeholder="/uploads/image.webp or https://…" class="admin-input mt-1.5"></label>@endfor</div>
        <label class="mt-4 block text-sm font-semibold">Upload images<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/avif" multiple class="mt-1.5 block w-full rounded-xl border border-slate-300 p-2 text-sm"></label>
    </section>
    <div class="flex flex-wrap gap-3"><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-700">{{ $editing ? 'Save changes' : 'Create product' }}</button><a href="{{ route('admin.products.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold">Cancel</a></div>
</form>
@endsection
