@php($wishlisted = in_array($product->id, session('wishlist', []), true))
<article class="product-card relative">
    <form action="{{ route('wishlist.toggle', $product) }}" method="POST" data-wishlist-form class="absolute right-3 top-3 z-10">@csrf
        <button type="submit" data-wishlist-button data-saved="{{ $wishlisted ? 'true' : 'false' }}" aria-label="{{ $wishlisted ? 'Remove from' : 'Add to' }} wishlist" aria-pressed="{{ $wishlisted ? 'true' : 'false' }}" class="grid size-8 place-items-center rounded-full border border-zinc-200 bg-white/95 text-xl text-brand shadow-sm {{ $wishlisted ? 'text-rose-500' : '' }}">♡</button>
    </form>
    <a href="{{ route('product.show', $product) }}" class="product-image" aria-label="View {{ $product->name }}">
        @if($product->primary_image)<img src="{{ filter_var($product->primary_image, FILTER_VALIDATE_URL) ? $product->primary_image : asset(ltrim($product->primary_image, '/')) }}" alt="{{ $product->images->first()?->alt ?: $product->name }}" loading="lazy" decoding="async" width="600" height="600">@else<span class="grid h-full place-items-center text-sm text-zinc-400">Image coming soon</span>@endif
    </a>
    <div class="flex flex-1 flex-col pt-3">
        <a href="{{ route('product.show', $product) }}" class="product-title" title="{{ $product->name }}">{{ $product->name }}</a>
        <div class="mt-auto py-2.5">
            @if($product->sale_price && (float)$product->sale_price < (float)$product->regular_price)<span class="product-price">৳{{ number_format((float)$product->sale_price, 0) }}</span> <del class="ml-2 text-xs text-zinc-400">৳{{ number_format((float)$product->regular_price, 0) }}</del>@else<span class="product-price">৳{{ number_format((float)$product->regular_price, 0) }}</span>@endif
        </div>
        <form action="{{ route('cart.store', $product) }}" method="POST" data-cart-form>@csrf<input type="hidden" name="quantity" value="1"><button type="submit" class="product-action flex w-full items-center justify-center gap-2 px-2 font-medium" @disabled($product->stock < 1)><span aria-hidden="true">🛒</span> {{ $product->stock > 0 ? 'Add To Cart' : 'Out of Stock' }}</button></form>
    </div>
</article>
