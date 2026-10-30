<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = AdminActivity::query()->with('actor')
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%'.$request->string('action').'%'))
            ->when($request->filled('search'), fn ($query) => $query->where('description', 'like', '%'.$request->string('search').'%'))
            ->latest('created_at')->paginate(40)->withQueryString();
        return view('admin.activity.index', compact('activities'));
    }
}
