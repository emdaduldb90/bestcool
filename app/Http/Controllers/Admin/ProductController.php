<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\AdminAudit;
use App\Support\StockLedger;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', 'in:active,draft'],
            'stock_level' => ['nullable', 'in:low,out,in-stock'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:newest,name,price-low,price-high,stock-low'],
        ]);
        $query = Product::query()->with(['category', 'images'])
            ->when($filters['search'] ?? null, function ($query, $term) {
                $query->where(fn ($builder) => $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")->orWhere('brand', 'like', "%{$term}%"));
            })
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['brand'] ?? null, fn ($query, $brand) => $query->where('brand', $brand))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['stock_level'] ?? null, function ($query, $level) {
                match ($level) {
                    'out' => $query->where('stock', 0),
                    'low' => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'),
                    'in-stock' => $query->where('stock', '>', 0)->whereRaw('stock > low_stock_threshold'),
                };
            })
            ->when(isset($filters['min_price']), fn ($query) => $query->whereRaw('COALESCE(sale_price, regular_price) >= ?', [$filters['min_price']]))
            ->when(isset($filters['max_price']), fn ($query) => $query->whereRaw('COALESCE(sale_price, regular_price) <= ?', [$filters['max_price']]));

        match ($filters['sort'] ?? 'newest') {
            'name' => $query->orderBy('name'),
            'price-low' => $query->orderByRaw('COALESCE(sale_price, regular_price) ASC'),
            'price-high' => $query->orderByRaw('COALESCE(sale_price, regular_price) DESC'),
            'stock-low' => $query->orderBy('stock'),
            default => $query->latest(),
        };

        return view('admin.products.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'categories' => Category::query()->orderBy('name')->get(),
            'brands' => Product::query()->whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
            'filters' => $filters,
        ]);
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
            if ((int) $product->stock > 0) {
                StockLedger::record($product, 0, (int) $product->stock, 'INITIAL', 'Initial product stock');
            }
            $this->saveImages($request, $product, $data);

            AdminAudit::record('product.created', 'Created product '.$product->name.'.', $product);

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
            $before = $product->only(['name', 'slug', 'brand', 'category_id', 'regular_price', 'sale_price', 'is_active']);
            $product->update($this->attributes($data, $product));
            $this->saveImages($request, $product, $data);
            AdminAudit::record('product.updated', 'Updated product '.$product->name.'.', $product, ['before' => $before, 'after' => $product->only(array_keys($before))]);
        });

        return redirect()->route('admin.products.edit', $product->fresh())->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        AdminAudit::record('product.deleted', 'Deleted product '.$product->name.'.', $product);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product removed from the catalog.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string', 'distinct', 'exists:products,id'],
            'action' => ['required', 'in:publish,hide'],
        ]);
        $active = $data['action'] === 'publish';
        $count = DB::transaction(fn () => Product::query()->whereIn('id', $data['ids'])->update(['is_active' => $active, 'updated_at' => now()]));
        AdminAudit::record('products.bulk_visibility', ($active ? 'Published ' : 'Hid ').$count.' products.', null, ['count' => $count, 'visible' => $active]);

        return back()->with('success', $count.' product(s) updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', 'in:active,draft'],
            'stock_level' => ['nullable', 'in:low,out,in-stock'],
        ]);
        $query = Product::query()->with('category')
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($b) => $b->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('brand', 'like', "%{$term}%")))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['brand'] ?? null, fn ($q, $brand) => $q->where('brand', $brand))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->when($filters['stock_level'] ?? null, function ($q, $level) {
                match ($level) {
                    'out' => $q->where('stock', 0),
                    'low' => $q->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'),
                    'in-stock' => $q->where('stock', '>', 0)->whereRaw('stock > low_stock_threshold'),
                };
            });

        AdminAudit::record('products.export', 'Exported a product inventory CSV.', null, ['filters' => $filters]);

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['ID', 'Name', 'SKU', 'Brand', 'Category', 'Regular price', 'Sale price', 'Stock', 'Low stock threshold', 'Status']);
            $query->orderBy('name')->chunk(500, function ($products) use ($output) {
                foreach ($products as $product) {
                    fputcsv($output, [$product->id, $product->name, $product->sku, $product->brand, $product->category?->name,
                        $product->regular_price, $product->sale_price, $product->stock, $product->low_stock_threshold, $product->is_active ? 'Active' : 'Draft']);
                }
            });
            fclose($output);
        }, 'products-inventory.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('products', 'slug')->ignore($product?->getKey())],
            'sku' => ['nullable', 'string', 'max:190', Rule::unique('products', 'sku')->ignore($product?->getKey())],
            'brand' => ['nullable', 'string', 'max:190'],
            'category_id' => ['required', 'exists:categories,id'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:regular_price'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'questions' => ['nullable', 'array', 'max:15'],
            'questions.*.question' => ['nullable', 'string', 'max:500'],
            'questions.*.answer' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
            'image_urls' => ['nullable', 'array', 'max:8'],
            'image_urls.*' => ['nullable', 'string', 'max:2048'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string', 'exists:product_images,id'],
            'primary_image_id' => ['nullable', 'string', 'exists:product_images,id'],
            'image_alt' => ['nullable', 'array', 'max:30'],
            'image_alt.*' => ['nullable', 'string', 'max:190'],
            'image_order' => ['nullable', 'array'],
            'image_order.*' => ['integer', 'min:0', 'max:10000'],
        ];
        if ($product) {
            $rules['stock'] = ['prohibited'];
        } else {
            $rules['stock'] = ['required', 'integer', 'min:0'];
        }
        $data = $request->validate($rules);

        foreach ($data['image_urls'] ?? [] as $url) {
            if ($url !== null && trim($url) !== '' && ! $this->safeImageUrl(trim($url))) {
                throw ValidationException::withMessages(['image_urls' => 'Use an https image URL or a path under /uploads/.']);
            }
        }
        foreach ($data['questions'] ?? [] as $index => $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if (($question === '') !== ($answer === '')) {
                throw ValidationException::withMessages(["questions.{$index}.question" => 'Enter both a question and its answer.']);
            }
        }
        if ($product && ! empty($data['primary_image_id']) && ! $product->images()->whereKey($data['primary_image_id'])->exists()) {
            throw ValidationException::withMessages(['primary_image_id' => 'Choose an image belonging to this product.']);
        }

        return $data;
    }

    private function attributes(array $data, ?Product $product): array
    {
        $attributes = [
            'id' => $product?->id ?? (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name'], $product?->id),
            'sku' => ($data['sku'] ?? '') ?: null,
            'brand' => ($data['brand'] ?? '') ?: null,
            'category_id' => $data['category_id'],
            'regular_price' => $data['regular_price'],
            'sale_price' => ($data['sale_price'] ?? null) ?: null,
            'low_stock_threshold' => $data['low_stock_threshold'],
            'description' => $data['description'] ?? null,
            'specifications' => $data['specifications'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'questions' => collect($data['questions'] ?? [])->map(fn ($item) => [
                'question' => trim((string) ($item['question'] ?? '')),
                'answer' => trim((string) ($item['answer'] ?? '')),
            ])->filter(fn ($item) => $item['question'] !== '' && $item['answer'] !== '')->values()->all(),
            'meta_title' => ($data['meta_title'] ?? '') ?: null,
            'meta_description' => ($data['meta_description'] ?? '') ?: null,
        ];
        if (! $product) {
            $attributes['stock'] = $data['stock'];
        }

        return $attributes;
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

        foreach ($data['image_order'] ?? [] as $imageId => $order) {
            $product->images()->whereKey($imageId)->update(['sort_order' => $order]);
        }
        foreach ($data['image_alt'] ?? [] as $imageId => $alt) {
            $product->images()->whereKey($imageId)->update(['alt' => trim((string) $alt) ?: null]);
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

        $images = $product->images()->get();
        $chosenPrimary = $data['primary_image_id'] ?? null;
        if (! $chosenPrimary || ! $images->contains('id', $chosenPrimary)) {
            $chosenPrimary = $images->firstWhere('is_primary', true)?->id ?? $images->first()?->id;
        }
        foreach ($images as $image) {
            if ($image->is_primary !== ($image->id === $chosenPrimary)) {
                $image->forceFill(['is_primary' => $image->id === $chosenPrimary])->save();
            }
        }
    }

    private function safeImageUrl(string $url): bool
    {
        return SafeUrl::image($url);
    }
}
