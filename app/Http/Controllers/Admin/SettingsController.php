<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\AdminAudit;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const FIELDS = [
        'site.name' => ['label' => 'Store name', 'max' => 120],
        'site.tagline' => ['label' => 'Tagline', 'max' => 255],
        'site.logo' => ['label' => 'Logo path or URL', 'max' => 2048, 'image' => true],
        'site.favicon' => ['label' => 'Favicon path or URL', 'max' => 2048, 'image' => true],
        'contact.phone' => ['label' => 'Phone', 'max' => 64],
        'contact.email' => ['label' => 'Contact email', 'max' => 190, 'email' => true],
        'contact.address' => ['label' => 'Address', 'max' => 500],
        'whatsapp.number' => ['label' => 'WhatsApp number', 'max' => 64],
        'whatsapp.message' => ['label' => 'WhatsApp greeting', 'max' => 255],
        'seo.defaultTitle' => ['label' => 'Default SEO title', 'max' => 190],
        'seo.defaultDescription' => ['label' => 'Default SEO description', 'max' => 500],
        'footer.text' => ['label' => 'Footer text', 'max' => 500],
        'home.about.heading' => ['label' => 'Homepage about heading', 'max' => 190],
        'home.about.html' => ['label' => 'Homepage about copy', 'max' => 10000],
    ];

    private const DEFAULTS = [
        'home.about.heading' => 'Everyday essentials. A better place to call home.',
        'home.about.html' => '<p>Explore air conditioners, kitchen appliances and home electronics from the brands you know. Compare your options, discover useful upgrades and choose what works for your home, your family and your budget.</p><h3>Comfort, for every season</h3><p>Find cooling solutions for your space and get in touch for help choosing.</p><h3>Make more of the everyday</h3><p>Discover practical kitchen and home appliances for your family.</p><h3>A team you can talk to</h3><p>Have a question about a product or order? Contact us or visit our showroom.</p>',
    ];

    public function edit(): View
    {
        $values = Setting::query()->get()->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value])->all();
        $values = array_replace(self::DEFAULTS, $values);
        $values['nav.header'] ??= [];
        $values['footer.columns'] ??= [];
        $values['social.links'] ??= [];
        $values['home.services'] ??= $this->defaultServices($values);

        return view('admin.settings.edit', ['fields' => self::FIELDS, 'values' => $values]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->validate(['settings' => ['required', 'array']])['settings'];
        foreach (self::FIELDS as $key => $field) {
            $value = $settings[$key] ?? '';
            $rules = ['nullable', 'string', 'max:'.$field['max']];
            if ($field['email'] ?? false) {
                $rules[] = 'email';
            }
            $validator = Validator::make(['value' => $value], ['value' => $rules]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['settings['.$key.']' => $validator->errors()->first('value')]);
            }
            if (in_array($key, ['home.about.html'], true)) {
                $settings[$key] = \App\Support\SafeHtml::clean((string) $value);
            }
            if (($field['image'] ?? false) && trim((string) $value) !== '' && ! $this->safeImage((string) $value)) {
                throw ValidationException::withMessages(['settings['.$key.']' => 'Use an https image URL or a local /uploads/ image path.']);
            }
        }

        $navLinks = $this->validateLinks($request->input('navLinks', []), 'navLinks', 12);
        $footerColumns = $this->validateFooter($request->input('footerColumns', []));
        $socialLinks = $this->validateSocial($request->input('socialLinks', []));
        $homeServices = $this->validateServices($request->input('homeServices', []));
        $uploaded = $this->saveBranding($request);

        foreach (self::FIELDS as $key => $field) {
            $this->save($key, trim((string) ($settings[$key] ?? '')));
        }
        $this->save('nav.header', $navLinks);
        $this->save('footer.columns', $footerColumns);
        $this->save('social.links', $socialLinks);
        $this->save('home.services', $homeServices);
        foreach (['site.logo', 'site.favicon'] as $key) {
            $value = trim((string) ($settings[$key] ?? ''));
            if (isset($uploaded[$key])) {
                $value = $uploaded[$key];
            }
            $this->save($key, $value);
        }

        Cache::forget('storefront.settings');
        AdminAudit::record('settings.updated', 'Updated public store settings.', null, ['sections' => ['general', 'branding', 'navigation', 'footer', 'contact', 'seo', 'homepage']]);

        return redirect()->route('admin.settings.edit')->with('success', 'Store settings saved.');
    }

    private function save(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    private function validateLinks(mixed $links, string $field, int $limit): array
    {
        if (! is_array($links) || count($links) > $limit) {
            throw ValidationException::withMessages([$field => 'Too many links were submitted.']);
        }
        $result = [];
        foreach ($links as $index => $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $href = trim((string) ($link['href'] ?? ''));
            if ($label === '' && $href === '') {
                continue;
            }
            if ($label === '' || mb_strlen($label) > 80 || ! $this->safeLink($href)) {
                throw ValidationException::withMessages(["{$field}.{$index}" => 'Enter a label and a safe site path or https, mailto, or tel link.']);
            }
            $result[] = ['label' => $label, 'href' => $href, 'active' => filter_var($link['active'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }

        return $result;
    }

    private function validateFooter(mixed $columns): array
    {
        if (! is_array($columns) || count($columns) > 5) {
            throw ValidationException::withMessages(['footerColumns' => 'Use up to five footer columns.']);
        }
        $result = [];
        foreach ($columns as $columnIndex => $column) {
            if (! is_array($column)) {
                continue;
            }
            $title = trim((string) ($column['title'] ?? ''));
            $links = $this->validateLinks($column['links'] ?? [], "footerColumns.{$columnIndex}.links", 12);
            if ($title === '' && $links === []) {
                continue;
            }
            if ($title === '' || mb_strlen($title) > 80) {
                throw ValidationException::withMessages(["footerColumns.{$columnIndex}.title" => 'Each footer column needs a short title.']);
            }
            $result[] = ['title' => $title, 'links' => $links, 'active' => filter_var($column['active'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }

        return $result;
    }

    private function validateSocial(mixed $links): array
    {
        if (! is_array($links) || count($links) > 10) {
            throw ValidationException::withMessages(['socialLinks' => 'Use up to ten social links.']);
        }
        $result = [];
        foreach ($links as $index => $link) {
            if (! is_array($link)) {
                continue;
            }
            $platform = trim((string) ($link['platform'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($platform === '' && $url === '') {
                continue;
            }
            if ($platform === '' || mb_strlen($platform) > 40 || ! $this->safeExternalUrl($url)) {
                throw ValidationException::withMessages(["socialLinks.{$index}" => 'Enter a platform and a valid https URL.']);
            }
            $result[] = ['platform' => $platform, 'url' => $url, 'active' => filter_var($link['active'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }

        return $result;
    }

    private function validateServices(mixed $services): array
    {
        if (! is_array($services) || count($services) > 8) {
            throw ValidationException::withMessages(['homeServices' => 'Use up to eight service tiles.']);
        }
        $result = [];
        foreach ($services as $index => $service) {
            if (! is_array($service)) {
                continue;
            }
            $title = trim((string) ($service['title'] ?? ''));
            $text = trim((string) ($service['text'] ?? ''));
            $href = trim((string) ($service['href'] ?? ''));
            if ($title === '' && $text === '' && $href === '') {
                continue;
            }
            if ($title === '' || $text === '' || ! $this->safeLink($href)) {
                throw ValidationException::withMessages(["homeServices.{$index}" => 'Each service tile needs a title, description and safe link.']);
            }
            $result[] = ['title' => mb_substr($title, 0, 80), 'text' => mb_substr($text, 0, 160), 'href' => $href, 'icon' => mb_substr((string) ($service['icon'] ?? '•'), 0, 8)];
        }

        return $result;
    }

    private function saveBranding(Request $request): array
    {
        $request->validate([
            'branding.logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'branding.favicon_file' => ['nullable', 'image', 'mimes:png,webp', 'max:1024'],
        ]);
        $stored = [];
        foreach (['site.logo' => 'branding.logo_file', 'site.favicon' => 'branding.favicon_file'] as $key => $field) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $directory = public_path('uploads/branding');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $image = $request->file($field);
            $filename = Str::uuid().'.'.$image->extension();
            $image->move($directory, $filename);
            $stored[$key] = '/uploads/branding/'.$filename;
        }

        return $stored;
    }

    private function defaultServices(array $values): array
    {
        return [
            ['icon' => '⌕', 'title' => 'Product Finder', 'text' => 'Find the right fit for your home', 'href' => '/shop'],
            ['icon' => '☎', 'title' => 'Talk to an Expert', 'text' => 'A little help choosing better', 'href' => ! empty($values['contact.phone']) ? 'tel:'.$values['contact.phone'] : '/contact-us'],
            ['icon' => '❄', 'title' => 'AC Collection', 'text' => 'Comfort for every space', 'href' => '/shop?category=air-conditioner'],
            ['icon' => '◉', 'title' => 'Customer Support', 'text' => 'We are here after your purchase', 'href' => '/contact-us'],
        ];
    }

    private function safeLink(string $link): bool
    {
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return ! str_contains($link, "\\");
        }
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        return match ($scheme) {
            'http', 'https' => $this->safeExternalUrl($link),
            'tel' => preg_match('/^tel:\+?[0-9(). -]+$/i', $link) === 1,
            'mailto' => filter_var(substr($link, 7), FILTER_VALIDATE_EMAIL) !== false,
            default => false,
        };
    }

    private function safeExternalUrl(string $url): bool
    {
        return SafeUrl::https($url);
    }

    private function safeImage(string $url): bool
    {
        return SafeUrl::image($url);
    }
}
