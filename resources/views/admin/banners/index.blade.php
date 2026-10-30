@extends('admin.layout')
@section('title', 'Homepage banners')
@section('page-title', 'Homepage banners')
@section('content')
<p class="mb-5 text-sm text-slate-500">Manage homepage hero banners. The lowest sort number appears first.</p>
<section class="mb-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="mb-4 font-bold">Add banner</h2>
    <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">@csrf
        <label class="text-sm font-semibold">Image path or URL<input name="image" placeholder="/uploads/banner.webp" class="admin-input mt-1.5"></label>
        <label class="text-sm font-semibold">Upload banner<input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/avif" class="mt-1.5 block w-full rounded-xl border border-slate-300 p-2 text-sm"></label>
        <label class="text-sm font-semibold">Alternative text<input name="alt" class="admin-input mt-1.5"></label>
        <label class="text-sm font-semibold">Click-through link<input name="link" value="/shop" class="admin-input mt-1.5"></label>
        <label class="text-sm font-semibold">Sort order<input name="sort_order" type="number" min="0" value="{{ (int) $banners->max('sort_order') + 1 }}" class="admin-input mt-1.5"></label>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_enabled" value="1" checked> Show on homepage</label>
        <div class="md:col-span-2"><button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Add banner</button></div>
    </form>
</section>

<div class="space-y-5">
@forelse($banners as $banner)
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex items-center justify-between"><h2 class="font-bold">Banner · #{{ $banner->sort_order }}</h2><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $banner->is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $banner->is_enabled ? 'Visible' : 'Hidden' }}</span></div>
        @php($content = $banner->content ?? [])
        @if(!empty($content['image']))<img src="{{ filter_var($content['image'], FILTER_VALIDATE_URL) ? $content['image'] : asset(ltrim($content['image'], '/')) }}" alt="" class="mb-4 max-h-44 w-full rounded-xl bg-slate-50 object-contain object-left">@endif
        <form id="banner-form-{{ $banner->id }}" method="POST" action="{{ route('admin.banners.update', $banner) }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">@csrf @method('PATCH')
            <label class="text-sm font-semibold">Image path or URL<input name="image" value="{{ $content['image'] ?? '' }}" class="admin-input mt-1.5"></label>
            <label class="text-sm font-semibold">Replace image<input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/avif" class="mt-1.5 block w-full rounded-xl border border-slate-300 p-2 text-sm"></label>
            <label class="text-sm font-semibold">Alternative text<input name="alt" value="{{ $content['alt'] ?? '' }}" class="admin-input mt-1.5"></label>
            <label class="text-sm font-semibold">Click-through link<input name="link" value="{{ $content['link'] ?? '/shop' }}" class="admin-input mt-1.5"></label>
            <label class="text-sm font-semibold">Sort order<input name="sort_order" type="number" min="0" value="{{ $banner->sort_order }}" class="admin-input mt-1.5"></label>
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_enabled" value="1" @checked($banner->is_enabled)> Show on homepage</label>
        </form>
        <div class="mt-4 flex flex-wrap gap-3"><button form="banner-form-{{ $banner->id }}" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Save banner</button><form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('Delete this banner?')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-bold text-rose-700">Delete</button></form></div>
    </section>
@empty<div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">No homepage banners found.</div>@endforelse
</div>
@endsection
