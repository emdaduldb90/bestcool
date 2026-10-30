<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = User::query()->where(function ($query) {
            $query->where('is_admin', true)->orWhereHas('roles');
        })->with('roles')->orderBy('name')->paginate(25);
        $roles = Role::query()->where('slug', '!=', 'super-admin')->orderBy('name')->get();
        return view('admin.staff.index', compact('staff', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);
        $role = Role::query()->whereKey($data['role_id'])->where('slug', '!=', 'super-admin')->firstOrFail();
        $user = new User();
        $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'is_admin' => false, 'is_active' => true]);
        $user->save();
        $user->roles()->attach($role->id, ['assigned_by' => $request->user()->id, 'assigned_at' => now()]);
        AdminAudit::record('staff.created', 'Created staff account '.$user->email.'.', $user, ['role' => $role->slug]);
        return back()->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->is_admin || $staff->roles()->exists(), 404);
        if ($staff->is_admin) return back()->withErrors(['staff' => 'Legacy Super Admin access is protected. Use the guarded admin:revoke command.']);
        $data = $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('slug', '!=', 'super-admin'))],
            'is_active' => ['required', 'boolean'],
        ]);
        $newRole = Role::query()->whereKey($data['role_id'])->firstOrFail();
        $isSuper = $staff->is_admin || $staff->hasRole('super-admin');
        $removingSuper = $isSuper && $newRole->slug !== 'super-admin';
        $disablingSuper = $isSuper && ! (bool) $data['is_active'];
        if (($removingSuper || $disablingSuper) && ($staff->id === $request->user()->id || $this->activeSuperAdmins()->whereKeyNot($staff->id)->doesntExist())) {
            return back()->withErrors(['role_id' => 'The last active Super Admin cannot be demoted or disabled.']);
        }
        if ($staff->id === $request->user()->id && ($removingSuper || $disablingSuper)) {
            return back()->withErrors(['role_id' => 'You cannot remove your own Super Admin access.']);
        }
        $staff->forceFill(['is_active' => (bool) $data['is_active']])->save();
        $staff->roles()->sync([$newRole->id => ['assigned_by' => $request->user()->id, 'assigned_at' => now()]]);
        AdminAudit::record('staff.permissions', 'Changed staff role for '.$staff->email.'.', $staff, ['role' => $newRole->slug, 'is_active' => $staff->is_active]);
        return back()->with('success', 'Staff access updated.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->is_admin || $staff->roles()->exists(), 404);
        if ($staff->is_admin) return back()->withErrors(['staff' => 'Legacy Super Admin access is protected. Use the guarded admin:revoke command.']);
        if ($staff->id === $request->user()->id || (($staff->is_admin || $staff->hasRole('super-admin')) && $this->activeSuperAdmins()->whereKeyNot($staff->id)->doesntExist())) {
            return back()->withErrors(['staff' => 'This account is the current or last active Super Admin and cannot be removed.']);
        }
        $staff->roles()->detach();
        if (! $staff->is_admin) $staff->delete();
        else $staff->forceFill(['is_active' => false])->save();
        AdminAudit::record('staff.removed', 'Removed staff access for '.$staff->email.'.', $staff);
        return back()->with('success', 'Staff access removed.');
    }

    private function activeSuperAdmins()
    {
        return User::query()->where('is_active', true)->where(function ($query) {
            $query->where('is_admin', true)->orWhereHas('roles', fn ($roles) => $roles->where('slug', 'super-admin'));
        });
    }
}
