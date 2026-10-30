<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\PageBlock;
use App\Models\Product;
use App\Models\Setting;
use App\Support\AdminAudit;
use App\Support\SafeHtml;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PageController extends Controller
{
    private const SLUGS = ['home', 'about-us', 'contact-us', 'privacy-policy', 'refund-policy', 'showroom', 'terms'];
    private const TYPES = ['BANNER', 'FEATURED_CATEGORIES', 'FEATURED_PRODUCTS', 'CATEGORY_PRODUCTS', 'TESTIMONIALS', 'RICH_TEXT', 'IMAGE_TEXT', 'IMAGE_GALLERY', 'FAQ', 'CONTACT_INFO', 'MAP_EMBED'];

    public function index(): View
    {
        $pages = ContentPage::query()->whereIn('slug', self::SLUGS)->withCount('blocks')->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")->orderBy('title')->get();
        return view('admin.pages.index', compact('pages'));
    }

    public function edit(ContentPage $page): View
    {
        abort_unless(in_array($page->slug, self::SLUGS, true), 404);
        $page->load('blocks');
        $categoryIds = $page->blocks->flatMap(fn (PageBlock $block) => (array) ($block->content['categoryIds'] ?? []))
            ->merge($page->blocks->pluck('content')->map(fn ($content) => $content['categoryId'] ?? null))->filter()->unique()->values();
        $productIds = $page->blocks->flatMap(fn (PageBlock $block) => (array) ($block->content['productIds'] ?? []))->filter()->unique()->values();
        $categories = Category::query()->whereIn('id', $categoryIds)->get(['id', 'name'])
            ->merge(Category::query()->whereNotIn('id', $categoryIds)->orderBy('name')->limit(100)->get(['id', 'name']))->unique('id')->values();
        $products = Product::query()->whereIn('id', $productIds)->get(['id', 'name', 'sku'])
            ->merge(Product::query()->whereNotIn('id', $productIds)->orderBy('name')->limit(200)->get(['id', 'name', 'sku']))->unique('id')->values();
        return view('admin.pages.edit', compact('page', 'categories', 'products'));
    }

    public function update(Request $request, ContentPage $page): RedirectResponse
    {
        abort_unless(in_array($page->slug, self::SLUGS, true), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::in(self::SLUGS)],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:2048'],
        ]);
        if ($data['slug'] !== $page->slug && ContentPage::query()->where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'That page slug is already in use.']);
        }
        if ($page->slug === 'home' && $data['slug'] !== 'home') {
            throw ValidationException::withMessages(['slug' => 'The homepage must keep its reserved home slug.']);
        }
        if (! empty($data['og_image']) && ! $this->safeImage($data['og_image'])) {
            throw ValidationException::withMessages(['og_image' => 'Use a secure image URL or a path under /uploads/.']);
        }
        $oldSlug = $page->slug;
        DB::transaction(function () use ($page, $data, $oldSlug) {
            $page->update($data);
            if ($oldSlug !== $data['slug']) $this->rewritePageLinks($oldSlug, $data['slug']);
        });
        Cache::forget('storefront.settings');
        AdminAudit::record('page.updated', 'Updated page metadata for '.$page->slug.'.', $page, ['slug' => $page->slug]);
        return back()->with('success', 'Page metadata saved.');
    }

    public function storeBlock(Request $request, ContentPage $page): RedirectResponse
    {
        abort_unless(in_array($page->slug, self::SLUGS, true), 404);
        $type = $request->validate(['type' => ['required', Rule::in(self::TYPES)]])['type'];
        $content = $this->blockContent($request, $type);
        $block = $page->blocks()->create([
            'id' => (string) Str::uuid(), 'type' => $type, 'content' => $content,
            'sort_order' => (int) $page->blocks()->max('sort_order') + 1, 'is_enabled' => true,
        ]);
        AdminAudit::record('page.block.created', 'Added '.$type.' section to '.$page->slug.'.', $block);
        return back()->with('success', 'Section added.');
    }

    public function updateBlock(Request $request, PageBlock $block): RedirectResponse
    {
        $type = $block->type;
        $content = $this->blockContent($request, $type);
        $enabled = $request->boolean('is_enabled');
        $block->update(['content' => $content, 'is_enabled' => $enabled]);
        AdminAudit::record('page.block.updated', 'Updated '.$type.' content.', $block, ['enabled' => $enabled]);
        return back()->with('success', 'Section saved.');
    }

    public function moveBlock(Request $request, PageBlock $block): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $blocks = $block->page->blocks()->orderBy('sort_order')->orderBy('id')->get();
        $from = $blocks->search(fn (PageBlock $item) => $item->id === $block->id);
        $to = max(0, min($blocks->count() - 1, $from + ($direction === 'up' ? -1 : 1)));
        if ($to !== $from) {
            $blocks->splice($to, 0, [$blocks->pull($from)]);
            DB::transaction(fn () => $blocks->values()->each(fn (PageBlock $item, int $order) => $item->update(['sort_order' => $order + 1])));
        }
        return back()->with('success', 'Section order updated.');
    }

    public function destroyBlock(PageBlock $block): RedirectResponse
    {
        AdminAudit::record('page.block.deleted', 'Deleted '.$block->type.' section.', $block);
        $block->delete();
        return back()->with('success', 'Section deleted.');
    }

    private function blockContent(Request $request, string $type): array
    {
        $input = (array) $request->input('content', []);
        $text = fn (string $key, int $max = 190, bool $required = false) => trim((string) $request->validate(["content.{$key}" => [$required ? 'required' : 'nullable', 'string', 'max:'.$max]])['content'][$key] ?? '');
        $image = function (string $key, bool $required = false) use ($request, $text) {
            $url = $text($key, 2048, $required);
            if ($url !== '' && ! $this->safeImage($url)) throw ValidationException::withMessages(["content.{$key}" => 'Use a secure image URL or path under /uploads/.']);
            return $url;
        };
        $link = function (string $key) use ($request, $text) {
            $url = $text($key, 2048);
            if ($url !== '' && ! $this->safeLink($url)) throw ValidationException::withMessages(["content.{$key}" => 'Enter a safe local path or https link.']);
            return $url;
        };
        $heading = $text('heading');
        $subheading = $text('subheading', 300);
        $content = [];

        if (in_array($type, ['FEATURED_CATEGORIES', 'FEATURED_PRODUCTS', 'CATEGORY_PRODUCTS', 'TESTIMONIALS', 'RICH_TEXT', 'IMAGE_TEXT', 'IMAGE_GALLERY', 'FAQ', 'CONTACT_INFO', 'MAP_EMBED'], true)) {
            $content['heading'] = $heading;
            if ($subheading !== '') $content['subheading'] = $subheading;
        }
        if ($type === 'BANNER') {
            $content = ['image' => $image('image', true), 'alt' => $text('alt', 250), 'link' => $link('link') ?: '/shop'];
        } elseif ($type === 'FEATURED_CATEGORIES') {
            $ids = $request->validate(['content.category_ids' => ['nullable', 'array', 'max:20'], 'content.category_ids.*' => ['string', 'exists:categories,id']])['content']['category_ids'] ?? [];
            $content['categoryIds'] = array_values($ids);
        } elseif (in_array($type, ['FEATURED_PRODUCTS', 'CATEGORY_PRODUCTS'], true)) {
            $ids = $request->validate(['content.product_ids' => ['nullable', 'array', 'max:30'], 'content.product_ids.*' => ['string', 'exists:products,id']])['content']['product_ids'] ?? [];
            $content['productIds'] = array_values($ids);
            $content['url'] = $link('url') ?: '/shop';
            if ($type === 'CATEGORY_PRODUCTS') {
                $category = $request->validate(['content.category_id' => ['nullable', 'string', 'exists:categories,id']])['content']['category_id'] ?? null;
                if ($category) $content['categoryId'] = $category;
            }
        } elseif ($type === 'TESTIMONIALS') {
            $items = $request->validate([
                'content.items' => ['nullable', 'array', 'max:20'], 'content.items.*.name' => ['nullable', 'string', 'max:120'],
                'content.items.*.location' => ['nullable', 'string', 'max:120'], 'content.items.*.text' => ['nullable', 'string', 'max:1000'],
                'content.items.*.rating' => ['nullable', 'integer', 'between:1,5'],
            ])['content']['items'] ?? [];
            $content['items'] = collect($items)->filter(fn ($item) => trim($item['name'] ?? '') !== '' && trim($item['text'] ?? '') !== '')
                ->map(fn ($item) => ['name' => trim($item['name']), 'location' => trim($item['location'] ?? ''), 'text' => trim($item['text']), 'rating' => (int) ($item['rating'] ?? 5)])->values()->all();
        } elseif (in_array($type, ['RICH_TEXT', 'IMAGE_TEXT'], true)) {
            $content['html'] = SafeHtml::clean($text('html', 20000));
            if ($type === 'IMAGE_TEXT') {
                $content['image'] = $image('image'); $content['alt'] = $text('alt', 250);
                $content['layout'] = $request->input('content.layout') === 'right' ? 'right' : 'left';
                $content['link'] = $link('link'); $content['linkLabel'] = $text('link_label', 80);
            }
        } elseif ($type === 'IMAGE_GALLERY') {
            $items = $request->validate(['content.images' => ['nullable', 'array', 'max:12'], 'content.images.*.url' => ['nullable', 'string', 'max:2048'], 'content.images.*.alt' => ['nullable', 'string', 'max:250']])['content']['images'] ?? [];
            $content['images'] = [];
            foreach ($items as $item) {
                $url = trim($item['url'] ?? '');
                if ($url === '') continue;
                if (! $this->safeImage($url)) throw ValidationException::withMessages(['content.images' => 'Gallery images must be https URLs or paths under /uploads/.']);
                $content['images'][] = ['url' => $url, 'alt' => trim($item['alt'] ?? '')];
            }
        } elseif ($type === 'FAQ') {
            $items = $request->validate(['content.items' => ['nullable', 'array', 'max:20'], 'content.items.*.question' => ['nullable', 'string', 'max:500'], 'content.items.*.answer' => ['nullable', 'string', 'max:5000']])['content']['items'] ?? [];
            $content['items'] = collect($items)->filter(fn ($item) => trim($item['question'] ?? '') !== '' && trim($item['answer'] ?? '') !== '')
                ->map(fn ($item) => ['question' => trim($item['question']), 'answer' => SafeHtml::clean($item['answer'])])->values()->all();
        } elseif ($type === 'CONTACT_INFO') {
            foreach (['phone' => 64, 'email' => 190, 'address' => 500, 'hours' => 190] as $key => $max) $content[$key] = $text($key, $max);
        } elseif ($type === 'MAP_EMBED') {
            $embed = $text('embed_url', 2048);
            if ($embed !== '' && ! str_starts_with($embed, 'https://www.google.com/maps/embed?')) throw ValidationException::withMessages(['content.embed_url' => 'Only Google Maps embed URLs are allowed.']);
            $content['embedUrl'] = $embed;
        }
        return $content;
    }

    private function safeImage(string $url): bool
    {
        return SafeUrl::image($url);
    }

    private function safeLink(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, "\\")) return true;
        return filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function rewritePageLinks(string $oldSlug, string $newSlug): void
    {
        $oldPath = '/'.$oldSlug;
        $newPath = '/'.$newSlug;
        foreach (['nav.header', 'footer.columns', 'home.services', 'home.about.html'] as $key) {
            $setting = Setting::query()->find($key);
            if (! $setting) continue;
            if ($key === 'home.about.html' && is_string($setting->value)) {
                $setting->value = $this->rewriteLinkValues($setting->value, $oldPath, $newPath, 'html');
            } elseif (is_array($setting->value)) {
                $setting->value = $this->rewriteLinkValues($setting->value, $oldPath, $newPath);
            } else {
                continue;
            }
            $setting->updated_at = now();
            $setting->save();
        }
        foreach (PageBlock::query()->get() as $block) {
            $content = $block->content ?? [];
            $updated = $this->rewriteLinkValues($content, $oldPath, $newPath);
            if ($updated !== $content) $block->update(['content' => $updated]);
        }
    }

    private function rewriteLinkValues(mixed $value, string $oldPath, string $newPath, ?string $key = null): mixed
    {
        if (is_array($value)) {
            foreach ($value as $childKey => $child) $value[$childKey] = $this->rewriteLinkValues($child, $oldPath, $newPath, (string) $childKey);
            return $value;
        }
        if (! is_string($value)) return $value;
        if (in_array($key, ['href', 'url', 'link'], true) && ($value === $oldPath || str_starts_with($value, $oldPath.'?'))) {
            return $newPath.substr($value, strlen($oldPath));
        }
        if ($key === 'html') {
            return str_replace(['href="'.$oldPath.'"', "href='".$oldPath."'"], ['href="'.$newPath.'"', "href='".$newPath."'"], $value);
        }
        return $value;
    }
}
