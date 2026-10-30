<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('panel-title', 'Tracking & AI') · Best Cool Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="tracking-panel min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white"><div class="mx-auto flex max-w-screen-2xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-7"><div><a href="{{ route('admin.dashboard') }}" class="text-xs font-semibold text-sky-700">← Back to Admin</a><h1 class="mt-1 text-xl font-bold">Tracking <span class="text-sky-700">&amp; AI</span></h1></div><div class="flex items-center gap-3"><span class="hidden text-xs text-slate-500 sm:inline">{{ auth()->user()->name }}</span><form action="{{ route('logout') }}" method="POST">@csrf<button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold hover:bg-slate-50">Sign out</button></form></div></div></header>
    @php($tabs = ['overview' => 'Overview', 'configuration' => 'Configuration', 'routing' => 'Event routing', 'events' => 'Events', 'analytics' => 'Analytics', 'links' => 'Link analytics', 'deliveries' => 'Deliveries', 'consent' => 'Privacy & consent', 'health' => 'System health', 'ai' => 'AI planning'])
    <nav aria-label="Tracking panel" class="tracking-panel-tabs border-b border-slate-200 bg-white"><div class="mx-auto flex max-w-screen-2xl gap-1 overflow-x-auto px-4 sm:px-7">@foreach($tabs as $key => $label)<a href="{{ route('admin.tracking.show', $key === 'overview' ? [] : ['section' => $key]) }}" aria-current="{{ $section === $key ? 'page' : 'false' }}" @class(['whitespace-nowrap border-b-2 px-3 py-3 text-xs font-semibold', 'border-sky-600 text-sky-700' => $section === $key, 'border-transparent text-slate-600 hover:text-slate-950' => $section !== $key])>{{ $label }}</a>@endforeach</div></nav>
    <main class="tracking-panel-main mx-auto max-w-screen-2xl p-4 sm:p-7">
        @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><p class="font-bold">Please check the form:</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="mb-5"><p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Best Cool Electronics / Admin / Tracking &amp; AI</p><h2 class="mt-1 text-2xl font-bold">@yield('panel-title')</h2></div>
        @yield('panel-content')
    </main>
</body>
</html>
