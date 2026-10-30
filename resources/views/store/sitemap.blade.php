{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($pages as $page)<url><loc>{{ $page->slug === 'home' ? route('home') : route('page.show', $page->slug) }}</loc>@if($page->updated_at)<lastmod>{{ $page->updated_at->toAtomString() }}</lastmod>@endif</url>@endforeach
@foreach($categories as $category)<url><loc>{{ route('shop.index', ['category' => $category->slug]) }}</loc>@if($category->updated_at)<lastmod>{{ $category->updated_at->toAtomString() }}</lastmod>@endif</url>@endforeach
@foreach($products as $product)<url><loc>{{ route('product.show', $product->slug) }}</loc>@if($product->updated_at)<lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>@endif</url>@endforeach
</urlset>
