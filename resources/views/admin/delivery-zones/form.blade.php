@extends('admin.layout')
@php($editing = (bool) $zone->exists)
@section('title', $editing ? 'Edit delivery zone' : 'Add delivery zone')
@section('page-title', $editing ? 'Edit delivery zone' : 'Add delivery zone')
@section('content')
<form method="POST" action="{{ $editing ? route('admin.delivery-zones.update', $zone) : route('admin.delivery-zones.store') }}" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">@csrf @if($editing) @method('PUT') @endif
    <label class="block text-sm font-semibold">Area name<input name="name" required value="{{ old('name', $zone->name) }}" class="admin-input mt-1.5"></label>
    <label class="mt-4 block text-sm font-semibold">Delivery charge (BDT)<input name="charge" type="number" min="0" step="0.01" required value="{{ old('charge', $zone->charge ?? 0) }}" class="admin-input mt-1.5"></label>
    <label class="mt-4 block text-sm font-semibold">Sort order<input name="sort_order" type="number" min="0" required value="{{ old('sort_order', $zone->sort_order ?? 0) }}" class="admin-input mt-1.5"></label>
    <label class="mt-4 inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $zone->is_active ?? true))> Available at checkout</label>
    <div class="mt-6 flex gap-3"><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white">{{ $editing ? 'Save zone' : 'Create zone' }}</button><a href="{{ route('admin.delivery-zones.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold">Cancel</a></div>
</form>
@endsection
