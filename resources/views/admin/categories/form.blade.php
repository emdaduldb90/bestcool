@extends('admin.layout')
@php($editing = $category->exists)
@section('title', $editing ? 'Edit category' : 'Add category')
@section('page-title', $editing ? 'Edit category' : 'Add category')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">@csrf @if($editing) @method('PUT') @endif
    <label class="block text-sm font-semibold">Name<input name="name" required value="{{ old('name', $category->name) }}" class="admin-input mt-1.5"></label>
    <label class="mt-4 block text-sm font-semibold">Slug<input name="slug" value="{{ old('slug', $category->slug) }}" placeholder="Auto-generated from name" class="admin-input mt-1.5"></label>
    <div class="mt-4 grid gap-4 sm:grid-cols-[140px_minmax(0,1fr)] sm:items-center"><div class="grid size-32 place-items-center overflow-hidden rounded-xl border bg-slate-50">@if($category->image)<img data-category-image-preview src="{{ filter_var($category->image, FILTER_VALIDATE_URL) ? $category->image : asset(ltrim($category->image, '/')) }}" alt="Current category image" class="h-full w-full object-cover">@else<img data-category-image-preview alt="Image preview" class="hidden h-full w-full object-cover"><span data-category-image-empty class="px-3 text-center text-xs text-slate-400">No image selected</span>@endif</div><div><label class="block text-sm font-semibold">Upload image<input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/avif" data-category-image-input class="mt-1.5 block w-full rounded-xl border border-slate-300 p-2 text-sm"></label><label class="mt-3 block text-sm font-semibold">Or use an HTTPS image URL / /uploads/ path<input name="image" value="{{ old('image', $category->image) }}" class="admin-input mt-1.5 w-full"></label></div></div>
    <label class="mt-4 block text-sm font-semibold">SEO title<input name="meta_title" maxlength="190" value="{{ old('meta_title', $category->meta_title) }}" class="admin-input mt-1.5"></label>
    <label class="mt-4 block text-sm font-semibold">SEO description<textarea name="meta_description" maxlength="500" rows="2" class="admin-input mt-1.5">{{ old('meta_description', $category->meta_description) }}</textarea></label>
    <label class="mt-4 block text-sm font-semibold">Display order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="admin-input mt-1.5"></label>
    <label class="mt-4 inline-flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))> Show in the storefront</label>
    <div class="mt-6 flex gap-3"><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white">{{ $editing ? 'Save category' : 'Create category' }}</button><a href="{{ route('admin.categories.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold">Cancel</a></div>
</form>
@endsection
