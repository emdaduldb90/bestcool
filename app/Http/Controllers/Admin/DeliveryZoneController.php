<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeliveryZoneController extends Controller
{
    public function index(): View
    {
        $zones = DeliveryZone::query()->withCount('orders')->orderBy('sort_order')->orderBy('name')->paginate(20);

        return view('admin.delivery-zones.index', compact('zones'));
    }

    public function create(): View
    {
        return view('admin.delivery-zones.form', ['zone' => new DeliveryZone(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $zone = DeliveryZone::query()->create(['id' => (string) Str::uuid()] + $this->attributes($data));
        AdminAudit::record('delivery_zone.created', 'Created delivery zone '.$zone->name.'.', $zone);

        return redirect()->route('admin.delivery-zones.index')->with('success', 'Delivery zone created.');
    }

    public function edit(DeliveryZone $deliveryZone): View
    {
        return view('admin.delivery-zones.form', ['zone' => $deliveryZone]);
    }

    public function update(Request $request, DeliveryZone $deliveryZone): RedirectResponse
    {
        $deliveryZone->update($this->attributes($this->validated($request)));
        AdminAudit::record('delivery_zone.updated', 'Updated delivery zone '.$deliveryZone->name.'.', $deliveryZone);

        return redirect()->route('admin.delivery-zones.index')->with('success', 'Delivery zone updated.');
    }

    public function destroy(DeliveryZone $deliveryZone): RedirectResponse
    {
        if ($deliveryZone->orders()->exists()) {
            return back()->with('error', 'This delivery zone is used by existing orders. Deactivate it instead.');
        }

        AdminAudit::record('delivery_zone.deleted', 'Deleted delivery zone '.$deliveryZone->name.'.', $deliveryZone);
        $deliveryZone->delete();

        return redirect()->route('admin.delivery-zones.index')->with('success', 'Delivery zone deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'charge' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'], 'charge' => $data['charge'],
            'sort_order' => $data['sort_order'], 'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
