@extends('admin.layout')
@section('page-title', 'Pages & homepage')
@section('content')
<div class="mb-5"><p class="text-sm text-slate-500">Manage public page SEO and existing content blocks. Changes appear on the storefront immediately.</p></div>
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Page</th><th class="px-4 py-3">Public path</th><th class="px-4 py-3">Sections</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($pages as $page)<tr><td class="px-4 py-3 font-bold">{{ $page->title }}</td><td class="px-4 py-3 text-slate-500">{{ $page->slug === 'home' ? '/' : '/'.$page->slug }}</td><td class="px-4 py-3">{{ $page->blocks_count }}</td><td class="px-4 py-3 text-right"><a href="{{ route('admin.pages.edit', $page) }}" class="font-bold text-sky-700">Edit page</a></td></tr>@endforeach</tbody></table></div></div>
@endsection
