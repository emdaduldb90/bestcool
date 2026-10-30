<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const FIELDS = [
        'site.name' => ['label' => 'Store name', 'max' => 120],
        'site.tagline' => ['label' => 'Tagline', 'max' => 255],
        'contact.phone' => ['label' => 'Phone', 'max' => 64],
        'contact.email' => ['label' => 'Contact email', 'max' => 190],
        'contact.address' => ['label' => 'Address', 'max' => 500],
        'whatsapp.number' => ['label' => 'WhatsApp number', 'max' => 64],
        'whatsapp.message' => ['label' => 'WhatsApp greeting', 'max' => 255],
        'seo.defaultTitle' => ['label' => 'Default SEO title', 'max' => 190],
        'seo.defaultDescription' => ['label' => 'Default SEO description', 'max' => 500],
    ];

    public function edit(): View
    {
        $values = Setting::query()->whereIn('key', array_keys(self::FIELDS))->get()->mapWithKeys(
            fn (Setting $setting) => [$setting->key => $setting->value]
        )->all();

        return view('admin.settings.edit', ['fields' => self::FIELDS, 'values' => $values]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->validate(['settings' => ['required', 'array']])['settings'];
        foreach (self::FIELDS as $key => $field) {
            $value = $settings[$key] ?? '';
            $validator = Validator::make(['value' => $value], ['value' => ['nullable', 'string', 'max:'.$field['max']]]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['settings['.$key.']' => $validator->errors()->first('value')]);
            }
        }

        foreach (self::FIELDS as $key => $field) {
            Setting::query()->updateOrCreate(['key' => $key], [
                'value' => trim((string) ($settings[$key] ?? '')),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('storefront.settings');

        return redirect()->route('admin.settings.edit')->with('success', 'Store settings saved.');
    }
}
