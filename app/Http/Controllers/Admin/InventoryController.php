<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Support\AdminAudit;
use App\Support\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'stock_level' => ['nullable', 'in:low,out,in-stock']]);
        $query = Product::query()->with('category')
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($b) => $b->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('brand', 'like', "%{$term}%")))
            ->when($filters['stock_level'] ?? null, function ($q, $level) {
                match ($level) {
                    'out' => $q->where('stock', 0),
                    'low' => $q->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'),
                    'in-stock' => $q->where('stock', '>', 0)->whereRaw('stock > low_stock_threshold'),
                };
            });
        AdminAudit::record('inventory.export', 'Exported inventory CSV.', null, ['filters' => $filters]);
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Product', 'SKU', 'Brand', 'Category', 'Available stock', 'Low stock threshold', 'Status']);
            $query->orderBy('name')->chunk(500, function ($products) use ($out) {
                foreach ($products as $product) fputcsv($out, [$product->name, $product->sku, $product->brand, $product->category?->name, $product->stock, $product->low_stock_threshold, $product->is_active ? 'Active' : 'Draft']);
            });
            fclose($out);
        }, 'inventory-stock.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'stock_level' => ['nullable', 'in:low,out,in-stock'],
        ]);
        $products = Product::query()->with('category')
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('brand', 'like', "%{$term}%")))
            ->when($filters['stock_level'] ?? null, function ($query, $level) {
                match ($level) {
                    'out' => $query->where('stock', 0),
                    'low' => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'),
                    'in-stock' => $query->where('stock', '>', 0)->whereRaw('stock > low_stock_threshold'),
                };
            })->orderBy('stock')->paginate(20)->withQueryString();
        $adjustments = StockAdjustment::query()->with(['product', 'actor'])->latest('created_at')->paginate(15, ['*'], 'adjustments_page');

        return view('admin.inventory.index', compact('products', 'adjustments', 'filters'));
    }

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'change_quantity' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'in:RESTOCK,CORRECTION,DAMAGED,RETURN,OTHER'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $result = DB::transaction(function () use ($product, $data) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $previous = (int) $locked->stock;
            $change = (int) $data['change_quantity'];
            if ($previous + $change < 0) {
                return false;
            }
            $locked->stock = $previous + $change;
            $locked->save();
            StockLedger::record($locked, $previous, $change, $data['reason'], $data['notes'] ?? null);
            AdminAudit::record('inventory.adjusted', 'Adjusted stock for '.$locked->name.'.', $locked, [
                'from' => $previous, 'change' => $change, 'to' => $locked->stock, 'reason' => $data['reason'],
            ]);

            return true;
        });

        if (! $result) {
            return back()->withErrors(['change_quantity' => 'This adjustment would make available stock negative.']);
        }

        return back()->with('success', 'Inventory updated and adjustment recorded.');
    }
}
