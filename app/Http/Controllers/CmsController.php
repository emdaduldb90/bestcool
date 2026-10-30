<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use Illuminate\View\View;

class CmsController extends Controller
{
    public function show(string $slug): View
    {
        $page = ContentPage::query()
            ->with(['blocks' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('store.page', compact('page'));
    }
}
