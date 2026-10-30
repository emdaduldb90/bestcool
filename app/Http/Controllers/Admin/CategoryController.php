<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
        $categories = Category::query()->withCount('products')->orderBy('name')->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Category::query()->create($this->attributes($data));
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
        $category->update($this->attributes($data, $category));
        cache()->forget('storefront.categories');

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Move or remove this category’s products before deleting it.');
        }

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
        ];
    }

    private function safeImageUrl(string $url): bool
    {
        if (str_starts_with($url, '/uploads/')) {
            return ! str_contains($url, '..');
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
