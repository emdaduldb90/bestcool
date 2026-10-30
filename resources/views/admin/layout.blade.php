<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · Best Cool Electronics</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[250px_minmax(0,1fr)]">
        <aside class="bg-slate-950 text-slate-200 lg:min-h-screen">
            <div class="flex items-center justify-between border-b border-white/10 px-5 py-5">
                <a href="{{ route('admin.dashboard') }}" class="text-lg font-extrabold tracking-tight text-white">Best Cool <span class="text-sky-400">Admin</span></a>
                <a href="{{ route('home') }}" class="text-xs text-slate-400 hover:text-white">View store ↗</a>
            </div>
            <nav class="flex gap-1 overflow-x-auto p-3 lg:block lg:space-y-1" aria-label="Admin navigation">
                @foreach([
                    ['admin.dashboard', 'Dashboard', 'admin.dashboard'],
                    ['admin.products.index', 'Products', 'admin.products.*'],
                    ['admin.categories.index', 'Categories', 'admin.categories.*'],
                    ['admin.orders.index', 'Orders', 'admin.orders.*'],
                    ['admin.delivery-zones.index', 'Delivery zones', 'admin.delivery-zones.*'],
                    ['admin.banners.index', 'Homepage banners', 'admin.banners.*'],
                    ['admin.settings.edit', 'Store settings', 'admin.settings.*'],
                ] as [$route, $label, $pattern])
                    <a href="{{ route($route) }}" @class(['flex shrink-0 items-center rounded-lg px-3 py-2.5 text-sm font-semibold transition hover:bg-white/10 hover:text-white', 'bg-sky-500/15 text-sky-300' => request()->routeIs($pattern), 'text-slate-400' => !request()->routeIs($pattern)])>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="hidden border-t border-white/10 p-4 text-xs leading-5 text-slate-500 lg:block">Store management<br>Signed in as {{ auth()->user()->name }}</div>
        </aside>

        <div class="min-w-0">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 sm:px-7">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Best Cool Electronics</p><h1 class="text-lg font-bold">@yield('page-title', 'Admin panel')</h1></div>
                <div class="flex items-center gap-3"><span class="hidden text-sm text-slate-600 sm:inline">{{ auth()->user()->email }}</span><form action="{{ route('logout') }}" method="POST">@csrf<button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold hover:bg-slate-50">Sign out</button></form></div>
            </header>
            <main class="mx-auto max-w-7xl p-4 sm:p-7">
                @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><p class="font-bold">Please check the form:</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
