@extends('admin.layout')
@section('title', 'Store settings')
@section('page-title', 'Store settings')
@section('content')
@php($submittedSettings = old('settings', $values))
<div class="mb-5"><p class="text-sm text-slate-500">Edit public contact and SEO details used across the storefront.</p></div>
<form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">@csrf @method('PUT')
    @foreach($fields as $key => $field)
        <label class="mb-4 block text-sm font-semibold">{{ $field['label'] }}
            @if(str_contains($key, 'Description') || str_contains($key, 'address') || str_contains($key, 'message'))<textarea name="settings[{{ $key }}]" rows="3" class="admin-input mt-1.5">{{ $submittedSettings[$key] ?? '' }}</textarea>
            @else<input name="settings[{{ $key }}]" value="{{ $submittedSettings[$key] ?? '' }}" class="admin-input mt-1.5">@endif
        </label>
    @endforeach
    <button class="mt-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white">Save settings</button>
</form>
@endsection
