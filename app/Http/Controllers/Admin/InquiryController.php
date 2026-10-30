<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $inquiries = Inquiry::query()->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%')->orWhere('phone', 'like', '%'.$request->string('search').'%')->orWhere('subject', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(25)->withQueryString();
        return view('admin.inquiries.index', compact('inquiries'));
    }

    public function show(Inquiry $inquiry): View
    {
        if ($inquiry->status === 'NEW') $inquiry->update(['status' => 'READ']);
        return view('admin.inquiries.show', compact('inquiry'));
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:NEW,READ,REPLIED,ARCHIVED'], 'admin_note' => ['nullable', 'string', 'max:5000']]);
        $inquiry->update($data);
        AdminAudit::record('inquiry.updated', 'Updated contact inquiry status.', $inquiry, ['status' => $inquiry->status]);
        return back()->with('success', 'Inquiry updated.');
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        AdminAudit::record('inquiry.deleted', 'Deleted a contact inquiry.', $inquiry);
        $inquiry->delete();
        return redirect()->route('admin.inquiries.index')->with('success', 'Inquiry deleted.');
    }
}
