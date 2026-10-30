<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Support\AdminAudit;
use App\Support\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    private const STATUSES = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'];
    private const TRANSITIONS = [
        'PENDING' => ['PROCESSING', 'CANCELLED'],
        'PROCESSING' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['DELIVERED'],
        'DELIVERED' => [], 'CANCELLED' => [],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)], 'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'delivery_zone_id' => ['nullable', 'string', 'exists:delivery_zones,id'],
            'sort' => ['nullable', 'in:newest,oldest,total-high,total-low,status'],
        ]);
        $orders = Order::query()->with('user')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $term) {
                $query->where(fn ($builder) => $builder->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
            })
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['delivery_zone_id'] ?? null, fn ($query, $id) => $query->where('delivery_zone_id', $id))
            ->when(($filters['sort'] ?? 'newest') === 'oldest', fn ($query) => $query->oldest())
            ->when(($filters['sort'] ?? 'newest') === 'total-high', fn ($query) => $query->orderByDesc('total'))
            ->when(($filters['sort'] ?? 'newest') === 'total-low', fn ($query) => $query->orderBy('total'))
            ->when(($filters['sort'] ?? 'newest') === 'status', fn ($query) => $query->orderBy('status')->latest())
            ->when(($filters['sort'] ?? 'newest') === 'newest', fn ($query) => $query->latest())->paginate(20)->withQueryString();
        return view('admin.orders.index', ['orders' => $orders, 'statuses' => self::STATUSES, 'filters' => $filters, 'zones' => DeliveryZone::query()->orderBy('name')->get()]);
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'user', 'deliveryZone', 'statusHistory.actor']);
        return view('admin.orders.show', ['order' => $order, 'statuses' => self::STATUSES, 'transitions' => self::TRANSITIONS[$order->status] ?? []]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', self::STATUSES)], 'note' => ['nullable', 'string', 'max:500']]);
        $result = $this->transition($order, $data['status'], $data['note'] ?? null);
        if ($result === false) return back()->withErrors(['status' => 'That order status change is not allowed.']);
        if ($result === null) return back()->with('success', 'Order already has that status.');
        return back()->with('success', 'Order status updated.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:100'], 'order_ids.*' => ['integer', 'exists:orders,id'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)], 'note' => ['nullable', 'string', 'max:500'],
        ]);
        $updated = 0; $skipped = 0;
        foreach (array_unique($data['order_ids']) as $id) {
            $result = $this->transition(Order::query()->findOrFail($id), $data['status'], $data['note'] ?? null);
            if ($result === false) $skipped++; elseif (is_array($result)) $updated++;
        }
        $message = "Updated {$updated} order(s).".($skipped ? " {$skipped} invalid transition(s) were skipped." : '');
        return back()->with($skipped ? 'error' : 'success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $ids = $request->validate(['order_ids' => ['required', 'array', 'min:1', 'max:500'], 'order_ids.*' => ['integer', 'exists:orders,id']])['order_ids'];
        return response()->streamDownload(function () use ($ids) {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Order number', 'Customer', 'Phone', 'Email', 'Address', 'District', 'Status', 'Payment method', 'Date', 'Subtotal BDT', 'Delivery BDT', 'Total BDT']);
            Order::query()->whereIn('id', array_unique($ids))->orderBy('id')->chunk(200, function ($orders) use ($out) {
                foreach ($orders as $order) fputcsv($out, [$order->order_number, $order->customer_name, $order->phone, $order->email, $order->address, $order->district, $order->status, $order->payment_method, $order->created_at?->toDateTimeString(), $order->subtotal, $order->delivery_fee, $order->total]);
            });
            fclose($out);
        }, 'selected-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Order $order, string $document): View
    {
        abort_unless(in_array($document, ['invoice', 'packing-slip'], true), 404);
        $order->load(['items', 'deliveryZone']);
        $store = Setting::query()->whereIn('key', ['site.name', 'contact.phone', 'contact.email', 'contact.address'])
            ->get()->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value])->all();
        return view('admin.orders.print', compact('order', 'document', 'store'));
    }

    private function transition(Order $order, string $target, ?string $note): array|false|null
    {
        $result = DB::transaction(function () use ($order, $target, $note) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $locked->status;
            if ($from === $target) return null;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) return false;
            if ($target === 'CANCELLED') {
                $locked->load(['items' => fn ($query) => $query->orderBy('product_id')]);
                foreach ($locked->items as $item) {
                    $product = $item->product_id ? Product::query()->lockForUpdate()->find($item->product_id) : null;
                    if (! $product) continue;
                    $previous = (int) $product->stock;
                    $product->stock += $item->quantity;
                    $product->sold_count = max(0, (int) $product->sold_count - (int) $item->quantity);
                    $product->save();
                    StockLedger::record($product, $previous, (int) $item->quantity, 'ORDER_CANCELLED', 'Returned from order '.$locked->order_number);
                }
            }
            $locked->update(['status' => $target]);
            OrderStatusHistory::query()->create(['order_id' => $locked->id, 'actor_id' => auth()->id(), 'from_status' => $from, 'to_status' => $target, 'note' => $note, 'created_at' => now()]);
            return [$from, $target, $locked->order_number];
        });
        if (is_array($result)) AdminAudit::record('order.status', 'Updated order '.$result[2].' status.', $order, ['from' => $result[0], 'to' => $result[1]]);
        return $result;
    }
}
