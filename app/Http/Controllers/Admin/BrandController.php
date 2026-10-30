<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Product::query()->whereNotNull('brand')->where('brand', '!=', '')
            ->when($request->filled('search'), fn ($query) => $query->where('brand', 'like', '%'.$request->string('search').'%'))
            ->select('brand')->selectRaw('COUNT(*) as products_count')->groupBy('brand')->orderBy('brand')->paginate(25)->withQueryString();
        return view('admin.brands.index', compact('brands'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['from' => ['required', 'string', 'max:190'], 'to' => ['required', 'string', 'max:190']]);
        Product::query()->where('brand', $data['from'])->update(['brand' => trim($data['to'])]);
        AdminAudit::record('brand.renamed', 'Renamed product brand.', null, ['from' => $data['from'], 'to' => trim($data['to'])]);
        return back()->with('success', 'Brand name updated on matching products.');
    }
}
