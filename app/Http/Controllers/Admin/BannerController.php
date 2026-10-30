<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\PageBlock;
use App\Support\AdminAudit;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function index(): View
    {
        $home = ContentPage::query()->where('slug', 'home')->with(['blocks' => fn ($query) => $query
            ->where('type', 'BANNER')->orderBy('sort_order')])->firstOrFail();

        return view('admin.banners.index', ['banners' => $home->blocks]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $page = ContentPage::query()->where('slug', 'home')->firstOrFail();
        $banner = $page->blocks()->create([
            'id' => (string) Str::uuid(), 'type' => 'BANNER', 'content' => $this->content($request, $data),
            'sort_order' => $data['sort_order'], 'is_enabled' => $request->boolean('is_enabled'),
        ]);
        AdminAudit::record('banner.created', 'Added a homepage banner.', $banner);

        return redirect()->route('admin.banners.index')->with('success', 'Homepage banner added.');
    }

    public function update(Request $request, PageBlock $banner): RedirectResponse
    {
        abort_unless($banner->type === 'BANNER', 404);
        $data = $this->validated($request, false);
        $banner->update([
            'content' => $this->content($request, $data, $banner),
            'sort_order' => $data['sort_order'], 'is_enabled' => $request->boolean('is_enabled'),
        ]);
        AdminAudit::record('banner.updated', 'Updated a homepage banner.', $banner);

        return redirect()->route('admin.banners.index')->with('success', 'Homepage banner updated.');
    }

    public function destroy(PageBlock $banner): RedirectResponse
    {
        abort_unless($banner->type === 'BANNER', 404);
        AdminAudit::record('banner.deleted', 'Deleted a homepage banner.', $banner);
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Homepage banner deleted.');
    }

    private function validated(Request $request, bool $required): array
    {
        $data = $request->validate([
            'image' => [$required ? 'required_without:image_file' : 'nullable', 'nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:190'],
            'link' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
        $image = trim((string) ($data['image'] ?? ''));
        if ($image !== '' && ! $this->safeImageUrl($image)) {
            throw ValidationException::withMessages(['image' => 'Use an https image URL or a path under /uploads/.']);
        }
        $link = trim((string) ($data['link'] ?? ''));
        if ($link !== '' && ! $this->safeLink($link)) {
            throw ValidationException::withMessages(['link' => 'Use a site path or an http, https, tel, or mailto link.']);
        }

        return $data;
    }

    private function content(Request $request, array $data, ?PageBlock $banner = null): array
    {
        $image = $this->storeImage($request) ?? ($data['image'] ?? $banner?->content['image'] ?? '');

        return [
            'image' => $image,
            'alt' => trim((string) ($data['alt'] ?? '')) ?: 'Best Cool Electronics',
            'link' => trim((string) ($data['link'] ?? '')) ?: '/shop',
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image_file')) {
            return null;
        }

        $directory = public_path('uploads/banners');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $image = $request->file('image_file');
        $filename = Str::uuid().'.'.$image->extension();
        $image->move($directory, $filename);

        return '/uploads/banners/'.$filename;
    }

    private function safeImageUrl(string $url): bool
    {
        return SafeUrl::image($url);
    }

    private function safeLink(string $link): bool
    {
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return ! str_contains($link, "\\");
        }

        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        return match ($scheme) {
            'http', 'https' => filter_var($link, FILTER_VALIDATE_URL) !== false,
            'tel' => preg_match('/^tel:\+?[0-9(). -]+$/i', $link) === 1,
            'mailto' => filter_var(substr($link, 7), FILTER_VALIDATE_EMAIL) !== false,
            default => false,
        };
    }
}
