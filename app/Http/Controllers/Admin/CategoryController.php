<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\AdminAudit;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = $this->storeCategoryImage($request, $data['image'] ?? null);
        $category = Category::query()->create($this->attributes($data));
        AdminAudit::record('category.created', 'Created category '.$category->name.'.', $category);
        cache()->forget('storefront.categories');

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $data['image'] = $this->storeCategoryImage($request, array_key_exists('image', $data) ? $data['image'] : $category->image);
        $category->update($this->attributes($data, $category));
        AdminAudit::record('category.updated', 'Updated category '.$category->name.'.', $category);
        cache()->forget('storefront.categories');

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Move or remove this category’s products before deleting it.');
        }

        AdminAudit::record('category.deleted', 'Deleted category '.$category->name.'.', $category);
        $category->delete();
        cache()->forget('storefront.categories');

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('categories', 'slug')->ignore($category?->getKey())],
            'image' => ['nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $image = trim((string) ($data['image'] ?? ''));
        if ($image !== '' && ! $this->safeImageUrl($image)) {
            throw ValidationException::withMessages(['image' => 'Use an https image URL or a path under /uploads/.']);
        }

        return $data;
    }

    private function attributes(array $data, ?Category $category = null): array
    {
        $baseSlug = Str::slug(($data['slug'] ?? '') ?: $data['name']) ?: 'category';
        $slug = $baseSlug;
        $suffix = 2;
        while (Category::query()->where('slug', $slug)->when($category, fn ($query) => $query->where('id', '!=', $category->id))->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return [
            'id' => $category?->id ?? (string) Str::uuid(),
            'name' => $data['name'], 'slug' => $slug, 'image' => trim((string) ($data['image'] ?? '')) ?: null,
            'sort_order' => $data['sort_order'] ?? $category?->sort_order ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? $category?->is_active ?? true),
            'meta_title' => ($data['meta_title'] ?? '') ?: null,
            'meta_description' => ($data['meta_description'] ?? '') ?: null,
        ];
    }

    private function safeImageUrl(string $url): bool
    {
        return SafeUrl::image($url);
    }

    private function storeCategoryImage(Request $request, ?string $currentImage): ?string
    {
        if (! $request->hasFile('image_file')) {
            return $currentImage;
        }

        $directory = public_path('uploads/categories');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $image = $request->file('image_file');
        $filename = Str::uuid().'.'.$image->extension();
        $image->move($directory, $filename);

        return '/uploads/categories/'.$filename;
    }
}
