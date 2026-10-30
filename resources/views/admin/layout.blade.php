<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'Admin') · Best Cool Electronics</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
@php($adminUser = auth()->user())
@php($unreadInquiries = $adminUser->hasPermission('customers.view') ? \App\Models\Inquiry::query()->where('status', 'NEW')->count() : 0)
<div class="min-h-screen lg:grid lg:grid-cols-[250px_minmax(0,1fr)]">
    <aside class="bg-slate-950 text-slate-200 lg:min-h-screen">
        <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
            <a href="{{ route('admin.dashboard') }}" class="text-lg font-extrabold tracking-tight text-white">Best Cool <span class="text-sky-400">Admin</span></a>
            <a href="{{ route('home') }}" class="text-xs text-slate-400 hover:text-white">View store</a>
        </div>
        @php($overviewOpen = request()->routeIs('admin.dashboard', 'admin.reports.*'))
        @php($catalogOpen = request()->routeIs('admin.products.*', 'admin.brands.*', 'admin.categories.*', 'admin.inventory.*'))
        @php($ordersOpen = request()->routeIs('admin.orders.*', 'admin.customers.*', 'admin.inquiries.*'))
        @php($contentOpen = request()->routeIs('admin.pages.*', 'admin.banners.*'))
        @php($setupOpen = request()->routeIs('admin.delivery-zones.*', 'admin.settings.*'))
        @php($adminOpen = request()->routeIs('admin.activity.*', 'admin.staff.*', 'admin.system.*', 'admin.tracking.*'))
        <nav id="admin-navigation" aria-label="Admin navigation" class="hidden space-y-2 p-3 lg:block">
            @if($adminUser->hasPermission('dashboard.view') || $adminUser->hasPermission('reports.view'))
                <section>
                    <button type="button" data-admin-group-toggle aria-expanded="{{ $overviewOpen ? 'true' : 'false' }}" aria-controls="admin-group-overview" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest text-slate-400 hover:bg-white/5 hover:text-white"><span>Overview</span><svg data-admin-chevron @class(['size-4 transition-transform', 'rotate-180' => $overviewOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div id="admin-group-overview" @class(['mt-1 space-y-1 pl-2', 'hidden' => !$overviewOpen])>
                        @if($adminUser->hasPermission('dashboard.view'))<a href="{{ route('admin.dashboard') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.dashboard')])>Dashboard</a>@endif
                        @if($adminUser->hasPermission('reports.view'))<a href="{{ route('admin.reports.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.reports.*')])>Reports</a>@endif
                    </div>
                </section>
            @endif
            @if($adminUser->hasPermission('products.manage') || $adminUser->hasPermission('categories.manage') || $adminUser->hasPermission('inventory.manage'))
                <section>
                    <button type="button" data-admin-group-toggle aria-expanded="{{ $catalogOpen ? 'true' : 'false' }}" aria-controls="admin-group-catalog" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest text-slate-400 hover:bg-white/5 hover:text-white"><span>Catalog</span><svg data-admin-chevron @class(['size-4 transition-transform', 'rotate-180' => $catalogOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div id="admin-group-catalog" @class(['mt-1 space-y-1 pl-2', 'hidden' => !$catalogOpen])>
                        @if($adminUser->hasPermission('products.manage'))<a href="{{ route('admin.products.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.products.*')])>Products</a><a href="{{ route('admin.brands.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.brands.*')])>Brands</a>@endif
                        @if($adminUser->hasPermission('categories.manage'))<a href="{{ route('admin.categories.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.categories.*')])>Categories</a>@endif
                        @if($adminUser->hasPermission('inventory.manage'))<a href="{{ route('admin.inventory.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.inventory.*')])>Inventory</a>@endif
                    </div>
                </section>
            @endif
            @if($adminUser->hasPermission('orders.view') || $adminUser->hasPermission('customers.view'))
                <section>
                    <button type="button" data-admin-group-toggle aria-expanded="{{ $ordersOpen ? 'true' : 'false' }}" aria-controls="admin-group-orders" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest text-slate-400 hover:bg-white/5 hover:text-white"><span>Customers &amp; orders</span><svg data-admin-chevron @class(['size-4 transition-transform', 'rotate-180' => $ordersOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div id="admin-group-orders" @class(['mt-1 space-y-1 pl-2', 'hidden' => !$ordersOpen])>
                        @if($adminUser->hasPermission('orders.view'))<a href="{{ route('admin.orders.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.orders.*')])>Orders</a>@endif
                        @if($adminUser->hasPermission('customers.view'))<a href="{{ route('admin.customers.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.customers.*')])>Customers</a><a href="{{ route('admin.inquiries.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.inquiries.*')])>Messages @if($unreadInquiries)<span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-[10px] text-white">{{ $unreadInquiries }}</span>@endif</a>@endif
                    </div>
                </section>
            @endif
            @if($adminUser->hasPermission('content.manage'))
                <section>
                    <button type="button" data-admin-group-toggle aria-expanded="{{ $contentOpen ? 'true' : 'false' }}" aria-controls="admin-group-content" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest text-slate-400 hover:bg-white/5 hover:text-white"><span>Content</span><svg data-admin-chevron @class(['size-4 transition-transform', 'rotate-180' => $contentOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div id="admin-group-content" @class(['mt-1 space-y-1 pl-2', 'hidden' => !$contentOpen])>
                        <a href="{{ route('admin.pages.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.pages.*')])>Pages &amp; home builder</a><a href="{{ route('admin.banners.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.banners.*')])>Homepage banners</a>
                    </div>
                </section>
            @endif
            @if($adminUser->hasPermission('settings.manage'))
                <section>
                    <button type="button" data-admin-group-toggle aria-expanded="{{ $setupOpen ? 'true' : 'false' }}" aria-controls="admin-group-setup" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest text-slate-400 hover:bg-white/5 hover:text-white"><span>Store setup</span><svg data-admin-chevron @class(['size-4 transition-transform', 'rotate-180' => $setupOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div id="admin-group-setup" @class(['mt-1 space-y-1 pl-2', 'hidden' => !$setupOpen])>
                        <a href="{{ route('admin.delivery-zones.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.delivery-zones.*')])>Delivery zones</a><a href="{{ route('admin.settings.edit') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.settings.*')])>Website settings</a>
                    </div>
                </section>
            @endif
            @if($adminUser->hasPermission('activity.view') || $adminUser->hasPermission('staff.manage') || $adminUser->hasPermission('system.view') || $adminUser->hasPermission('tracking.view'))
                <section>
                    <button type="button" data-admin-group-toggle aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="admin-group-administration" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest text-slate-400 hover:bg-white/5 hover:text-white"><span>Administration</span><svg data-admin-chevron @class(['size-4 transition-transform', 'rotate-180' => $adminOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div id="admin-group-administration" @class(['mt-1 space-y-1 pl-2', 'hidden' => !$adminOpen])>
                        @if($adminUser->hasPermission('activity.view'))<a href="{{ route('admin.activity.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.activity.*')])>Activity log</a>@endif
                        @if($adminUser->hasPermission('staff.manage'))<a href="{{ route('admin.staff.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.staff.*')])>Staff &amp; roles</a>@endif
                        @if($adminUser->hasPermission('system.view'))<a href="{{ route('admin.system.index') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.system.*')])>System status</a>@endif
                        @if($adminUser->hasPermission('tracking.view'))<a href="{{ route('admin.tracking.show') }}" @class(['admin-nav-link', 'admin-nav-active' => request()->routeIs('admin.tracking.*')])>Tracking &amp; AI</a>@endif
                    </div>
                </section>
            @endif
        </nav>
        <div class="hidden border-t border-white/10 p-4 text-xs leading-5 text-slate-500 lg:block">Signed in as {{ $adminUser->name }}</div>
    </aside>
    <div class="min-w-0">
        <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-7">
            <button type="button" data-admin-menu-toggle aria-expanded="false" aria-controls="admin-navigation" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-lg lg:hidden" aria-label="Toggle admin menu">☰</button>
            <div class="min-w-0 flex-1"><nav aria-label="Breadcrumb" class="hidden text-[10px] font-semibold uppercase tracking-wider text-slate-400 sm:block"><ol class="flex items-center gap-1.5"><li><a href="{{ route('home') }}" class="hover:text-sky-700">Store</a></li><li aria-hidden="true">/</li><li><a href="{{ route('admin.dashboard') }}" class="hover:text-sky-700">Admin</a></li><li aria-hidden="true">/</li><li aria-current="page">@yield('page-title', 'Panel')</li></ol></nav><h1 class="truncate text-lg font-bold">@yield('page-title', 'Admin panel')</h1></div>
            @if($adminUser->hasPermission('products.manage'))<form action="{{ route('admin.products.index') }}" method="GET" class="hidden min-w-44 flex-1 md:block"><label class="sr-only" for="admin-search">Search products</label><input id="admin-search" type="search" name="search" value="{{ request('search') }}" placeholder="Search catalog..." class="admin-input w-full"></form>@endif
            @if($adminUser->hasPermission('customers.view'))<a href="{{ route('admin.inquiries.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50">Messages @if($unreadInquiries)<span class="ml-1 rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] text-white">{{ $unreadInquiries }}</span>@endif<span class="sr-only"> unread contact messages</span></a>@endif
            <a href="{{ route('admin.profile.edit') }}" class="hidden text-sm font-semibold text-slate-600 hover:text-sky-700 sm:block">{{ $adminUser->name }}</a>
            <form action="{{ route('logout') }}" method="POST">@csrf<button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold hover:bg-slate-50">Sign out</button></form>
        </header>
        <main class="mx-auto max-w-7xl p-4 sm:p-7">
            @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><p class="font-bold">Please check the form:</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
