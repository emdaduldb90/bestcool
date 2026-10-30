@extends('admin.layout')
@php($editing = $category->exists)
@section('title', $editing ? 'Edit category' : 'Add category')
@section('page-title', $editing ? 'Edit category' : 'Add category')
@section('content')
<form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">@csrf @if($editing) @method('PUT') @endif
    <label class="block text-sm font-semibold">Name<input name="name" required value="{{ old('name', $category->name) }}" class="admin-input mt-1.5"></label>
    <label class="mt-4 block text-sm font-semibold">Slug<input name="slug" value="{{ old('slug', $category->slug) }}" placeholder="Auto-generated from name" class="admin-input mt-1.5"></label>
    <label class="mt-4 block text-sm font-semibold">Image URL or /uploads/ path<input name="image" value="{{ old('image', $category->image) }}" class="admin-input mt-1.5"></label>
    <div class="mt-6 flex gap-3"><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white">{{ $editing ? 'Save category' : 'Create category' }}</button><a href="{{ route('admin.categories.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold">Cancel</a></div>
</form>
@endsection
