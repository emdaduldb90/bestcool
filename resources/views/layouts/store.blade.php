<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $siteSettings['seo.defaultTitle'] ?? 'Best Cool Electronics')</title>
    <meta name="description" content="@yield('description', $siteSettings['seo.defaultDescription'] ?? 'Genuine electronics with official warranty and delivery across Bangladesh.')">
    @if(!empty($siteSettings['site.favicon']))
        <link rel="icon" href="{{ asset(ltrim($siteSettings['site.favicon'], '/')) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:bg-white focus:p-3">Skip to content</a>
    <header class="site-header">
        <div class="hidden bg-white md:block">
            <div class="site-width flex items-center gap-5 py-3.5">
                <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2" aria-label="{{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }} home">
                    @if(!empty($siteSettings['site.logo']))
                        <img src="{{ asset(ltrim($siteSettings['site.logo'], '/')) }}" alt="{{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }}" class="h-10 w-auto object-contain" width="170" height="44">
                    @else
                        <span class="grid size-9 place-items-center rounded-xl bg-brand text-xl font-bold text-white">B</span>
                        <span class="text-lg font-extrabold text-ink">{{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }}</span>
                    @endif
                </a>
                <form action="{{ route('shop.index') }}" method="GET" class="relative ml-2 flex-1">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search for products, brands and more…" class="search-input w-full rounded-full py-2.5 pl-5 pr-12 text-sm" aria-label="Search products">
                    <button class="absolute right-1.5 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-brand text-white hover:bg-brand-dark" aria-label="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
                </form>
                <div class="hidden items-center gap-5 xl:flex">
                    @if(!empty($siteSettings['contact.phone'])) <a href="tel:{{ $siteSettings['contact.phone'] }}" class="flex items-center gap-2 text-sm font-bold text-ink"><span class="grid size-9 place-items-center rounded-full bg-brand-soft text-brand">☎</span><span><small class="block text-[11px] font-normal text-zinc-400">Hotline</small>{{ $siteSettings['contact.phone'] }}</span></a> @endif
                    @if(!empty($siteSettings['contact.email'])) <a href="mailto:{{ $siteSettings['contact.email'] }}" class="flex items-center gap-2 text-sm font-bold text-ink"><span class="grid size-9 place-items-center rounded-full bg-brand-soft text-brand">✉</span><span><small class="block text-[11px] font-normal text-zinc-400">Email</small>{{ $siteSettings['contact.email'] }}</span></a> @endif
                </div>
                <div class="flex items-center gap-1 text-zinc-600">
                    @if(auth()->user()?->isAdmin())<a href="{{ route('admin.dashboard') }}" class="rounded-full px-2 py-2 text-xs font-bold text-brand hover:bg-brand-soft">Admin</a>@endif
                    <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="rounded-full p-2 hover:text-brand" aria-label="Account">♙</a>
                    <a href="{{ route('wishlist.index') }}" class="relative rounded-full p-2 hover:text-brand" aria-label="Wishlist">♡<span data-wishlist-count @if($wishlistCount < 1) hidden @endif class="absolute -right-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $wishlistCount }}</span></a>
                    <a href="{{ route('cart.index') }}" class="relative rounded-full p-2 hover:text-brand" aria-label="Cart">🛒<span data-cart-count @if($cartCount < 1) hidden @endif class="absolute -right-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-brand px-1 text-[10px] font-bold text-white">{{ $cartCount }}</span></a>
                </div>
            </div>
            <div class="border-t border-zinc-100">
                <div class="site-width flex items-stretch">
                    <details class="group relative"><summary class="flex cursor-pointer list-none items-center gap-2 bg-ink px-5 py-3 text-sm font-bold text-white hover:bg-brand-dark">☷ <span>Top Categories</span> <span>⌄</span></summary>
                        <div class="absolute left-0 top-full z-50 min-w-64 rounded-b-lg border border-zinc-200 bg-white p-2 shadow-xl">
                            @foreach($siteCategories as $category)<a href="{{ route('shop.index', ['category' => $category->slug]) }}" class="block rounded px-3 py-2 text-sm hover:bg-brand-soft hover:text-brand">{{ $category->name }}</a>@endforeach
                        </div>
                    </details>
                    <nav class="flex items-center" aria-label="Main navigation">
                        @foreach(($siteSettings['nav.header'] ?? []) as $link)<a href="{{ $link['href'] ?? '/' }}" class="px-4 py-3 text-sm font-semibold text-zinc-700 hover:text-brand">{{ $link['label'] ?? '' }}</a>@endforeach
                    </nav>
                </div>
            </div>
        </div>
        <div class="bg-white md:hidden">
            <div class="flex items-center justify-between px-4 py-3">
                <details class="relative"><summary class="cursor-pointer list-none text-2xl text-ink" aria-label="Open menu">☰</summary>
                    <div class="absolute left-0 top-10 z-50 w-72 rounded-lg border border-zinc-200 bg-white p-3 shadow-xl">
                        @foreach(($siteSettings['nav.header'] ?? []) as $link)<a href="{{ $link['href'] ?? '/' }}" class="block rounded px-3 py-2 text-sm font-semibold hover:bg-brand-soft">{{ $link['label'] ?? '' }}</a>@endforeach
                        <hr class="my-2">@foreach($siteCategories as $category)<a href="{{ route('shop.index', ['category' => $category->slug]) }}" class="block rounded px-3 py-2 text-sm text-zinc-600 hover:bg-brand-soft">{{ $category->name }}</a>@endforeach
                    </div>
                </details>
                <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="Home">
                    @if(!empty($siteSettings['site.logo']))<img src="{{ asset(ltrim($siteSettings['site.logo'], '/')) }}" alt="{{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }}" class="h-9 w-auto object-contain" width="150" height="40">@else<span class="text-base font-extrabold text-ink">{{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }}</span>@endif
                </a>
                <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="text-xl text-zinc-700" aria-label="Account">♙</a>
            </div>
            <form action="{{ route('shop.index') }}" method="GET" class="px-4 pb-3"><input type="search" name="search" value="{{ request('search') }}" placeholder="Search products, brands and more…" class="search-input w-full rounded-full px-5 py-2.5 text-sm" aria-label="Search products"></form>
        </div>
    </header>

    @if(session('success')) <div data-live-notice class="flash-message" role="status">{{ session('success') }}</div> @else <div data-live-notice class="flash-message" role="status" hidden></div> @endif
    @if(session('error')) <div class="fixed left-1/2 top-16 z-50 -translate-x-1/2 rounded-full bg-rose-700 px-4 py-2 text-sm text-white" role="alert">{{ session('error') }}</div> @endif
    <main id="main-content" class="min-h-[65vh] pb-16 lg:pb-0">@yield('content')</main>

    <footer class="bg-ink text-zinc-300">
        <div class="site-width grid gap-9 py-11 sm:grid-cols-2 lg:grid-cols-4">
            <div><a href="{{ route('home') }}" class="text-xl font-extrabold text-white">{{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }}</a><p class="mt-4 text-sm leading-7 text-zinc-400">{{ $siteSettings['footer.text'] ?? $siteSettings['site.tagline'] ?? 'Genuine electronics with official warranty.' }}</p></div>
            @foreach(($siteSettings['footer.columns'] ?? []) as $column)<div><h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-white">{{ $column['title'] ?? '' }}</h2><ul class="space-y-3 text-sm">@foreach(($column['links'] ?? []) as $link)<li><a href="{{ $link['href'] ?? '/' }}" class="text-zinc-400 hover:text-white">{{ $link['label'] ?? '' }}</a></li>@endforeach</ul></div>@endforeach
            <div><h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-white">Contact</h2><ul class="space-y-3 text-sm text-zinc-400">@if(!empty($siteSettings['contact.phone']))<li><a href="tel:{{ $siteSettings['contact.phone'] }}">{{ $siteSettings['contact.phone'] }}</a></li>@endif @if(!empty($siteSettings['contact.email']))<li><a href="mailto:{{ $siteSettings['contact.email'] }}">{{ $siteSettings['contact.email'] }}</a></li>@endif @if(!empty($siteSettings['contact.address']))<li>{{ $siteSettings['contact.address'] }}</li>@endif</ul></div>
        </div>
        <div class="border-t border-white/10 py-5 text-center text-xs text-zinc-500">© {{ date('Y') }} {{ $siteSettings['site.name'] ?? 'Best Cool Electronics' }}. All rights reserved.</div>
    </footer>

    <nav @class(['fixed inset-x-0 bottom-0 z-40 grid border-t border-zinc-200 bg-white py-2 text-center text-[11px] font-medium text-zinc-600 shadow-[0_-2px_12px_#1018280a] lg:hidden', 'grid-cols-4' => !auth()->user()?->isAdmin(), 'grid-cols-5' => auth()->user()?->isAdmin()]) aria-label="Mobile navigation">
        <a href="{{ route('home') }}">⌂<span class="block">Home</span></a><a href="{{ route('shop.index') }}">⌕<span class="block">Shop</span></a><a href="{{ route('cart.index') }}">🛒<span class="block">Cart</span></a><a href="{{ auth()->check() ? route('account.index') : route('login') }}">♙<span class="block">Account</span></a>@if(auth()->user()?->isAdmin())<a href="{{ route('admin.dashboard') }}">⚙<span class="block">Admin</span></a>@endif
    </nav>
    @if(!empty($siteSettings['whatsapp.number']))<a href="https://wa.me/{{ preg_replace('/\D+/', '', $siteSettings['whatsapp.number']) }}?text={{ urlencode($siteSettings['whatsapp.message'] ?? 'Hello Best Cool!') }}" target="_blank" rel="noopener" class="fixed bottom-20 right-4 z-30 grid size-12 place-items-center rounded-full bg-green-500 text-2xl text-white shadow-lg lg:bottom-6" aria-label="Chat on WhatsApp">◉</a>@endif
</body>
</html>
