<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $validOrders = fn ($query) => $query->where('status', '!=', 'CANCELLED');
        $customers = User::query()->where('is_admin', false)->whereDoesntHave('roles')
            ->withCount(['orders' => $validOrders])->withSum(['orders as order_spend' => $validOrders], 'total')
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $customer): View
    {
        abort_if($customer->is_admin || $customer->roles()->exists(), 404);
        $orders = $customer->orders()->with('items')->latest()->paginate(15);
        return view('admin.customers.show', compact('customer', 'orders'));
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->is_admin || $customer->roles()->exists(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$customer->id],
            'is_active' => ['required', 'boolean'],
        ]);
        $customer->forceFill($data)->save();
        AdminAudit::record('customer.updated', 'Updated customer account '.$customer->email.'.', $customer, ['is_active' => $customer->is_active]);
        return back()->with('success', 'Customer details updated.');
    }
}
