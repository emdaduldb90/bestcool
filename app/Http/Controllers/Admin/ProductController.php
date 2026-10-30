<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()->with(['category', 'images'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim($request->string('search')->toString());
                $query->where(fn ($builder) => $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%"));
            })
            ->latest()->paginate(15)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        $product = new Product(['stock' => 0, 'low_stock_threshold' => 5, 'is_active' => true]);
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.products.form', compact('product', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::query()->create($this->attributes($data, null));
            $this->saveImages($request, $product, $data);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load('images');
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.products.form', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        DB::transaction(function () use ($request, $product, $data) {
            $product->update($this->attributes($data, $product));
            $this->saveImages($request, $product, $data);
        });

        return redirect()->route('admin.products.edit', $product->fresh())->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product removed from the catalog.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('products', 'slug')->ignore($product?->getKey())],
            'sku' => ['nullable', 'string', 'max:190', Rule::unique('products', 'sku')->ignore($product?->getKey())],
            'brand' => ['nullable', 'string', 'max:190'],
            'category_id' => ['required', 'exists:categories,id'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:regular_price'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'image_urls' => ['nullable', 'array', 'max:8'],
            'image_urls.*' => ['nullable', 'string', 'max:2048'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string'],
        ]);

        foreach ($data['image_urls'] ?? [] as $url) {
            if ($url !== null && trim($url) !== '' && ! $this->safeImageUrl(trim($url))) {
                throw ValidationException::withMessages(['image_urls' => 'Use an https image URL or a path under /uploads/.']);
            }
        }

        return $data;
    }

    private function attributes(array $data, ?Product $product): array
    {
        return [
            'id' => $product?->id ?? (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name'], $product?->id),
            'sku' => ($data['sku'] ?? '') ?: null,
            'brand' => ($data['brand'] ?? '') ?: null,
            'category_id' => $data['category_id'],
            'regular_price' => $data['regular_price'],
            'sale_price' => ($data['sale_price'] ?? null) ?: null,
            'stock' => $data['stock'],
            'low_stock_threshold' => $data['low_stock_threshold'],
            'description' => $data['description'] ?? null,
            'specifications' => $data['specifications'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function uniqueSlug(string $value, ?string $ignoreId): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function saveImages(Request $request, Product $product, array $data): void
    {
        $removeIds = $data['remove_images'] ?? [];
        if ($removeIds !== []) {
            $product->images()->whereIn('id', $removeIds)->delete();
        }

        $sortOrder = (int) $product->images()->max('sort_order') + 1;
        foreach ($data['image_urls'] ?? [] as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            $product->images()->create([
                'id' => (string) Str::uuid(), 'url' => $url, 'alt' => $product->name, 'sort_order' => $sortOrder++,
            ]);
        }

        if ($request->hasFile('images')) {
            $directory = public_path('uploads/products');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            foreach ($request->file('images') as $image) {
                $filename = Str::uuid().'.'.$image->extension();
                $image->move($directory, $filename);
                $product->images()->create([
                    'id' => (string) Str::uuid(), 'url' => '/uploads/products/'.$filename,
                    'alt' => $product->name, 'sort_order' => $sortOrder++,
                ]);
            }
        }
    }

    private function safeImageUrl(string $url): bool
    {
        if (str_starts_with($url, '/uploads/')) {
            return ! str_contains($url, '..');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) $scheme), ['http', 'https'], true);
    }
}
