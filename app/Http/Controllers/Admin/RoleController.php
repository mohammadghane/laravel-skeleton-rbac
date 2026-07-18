<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::query()
            ->when(!$request->user()->isAdmin(), fn ($q) => $q->where('center_id', $request->user()->center_id))
            ->where('hidden', false)
            ->when($request->filled('filters.name'), fn ($q) => $q->where('name', 'like', '%' . $request->input('filters.name') . '%'))
            ->paginate(config('app.page_size', 20))
            ->withQueryString();

        // AJAX pagination/filter reload -> return just the table partial (old rowsOnly flag)
        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.roles.partials.table', compact('roles'));
        }

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Renders the add/edit modal form. GET shows the form, POST persists it.
     * Old code multiplexed both behind the same `add($id)` action returning JSON; kept that shape.
     */
    public function edit(?Role $role, Request $request)
    {
        if ($role && !$request->user()->isAdmin() && $role->center_id !== $request->user()->center_id) {
            abort(404);
        }

        if ($request->ajax()) {
            return view('admin.roles.form', compact('role'));
        }

        return view('admin.modal-page', [
            'title' => $role ? __('ویرایش نقش سازمانی') : __('نقش سازمانی جدید'),
            'view' => 'admin.roles.form',
            'data' => compact('role'),
        ]);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (!$request->user()->isAdmin()) {
            $data['center_id'] = $request->user()->center_id;
        }

        $role = Role::updateOrCreate(['id' => $request->input('id')], $data);

        return response()->json([
            'success' => true,
            'message' => __('اطلاعات با موفقیت ذخیره شد.'),
            'id' => $role->id,
        ]);
    }

    public function destroy(Role $role, Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin() && $role->center_id !== $request->user()->center_id) {
            abort(404);
        }

        $role->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Show / save the tabbed permission-assignment form for a role.
     * Replaces RoleController::rolePerms() + Perms::savePerms().
     */
    public function permissions(Role $role, Request $request): View
    {
        $modules = config('permissions');
        $current = $role->permissions()->pluck('key')->all();

        if ($request->ajax()) {
            return view('admin.roles.permissions', compact('role', 'modules', 'current'));
        }

        return view('admin.modal-page', [
            'title' => __('دسترسی‌های نقش') . ": {$role->name}",
            'view' => 'admin.roles.permissions',
            'data' => compact('role', 'modules', 'current'),
        ]);
    }

    public function savePermissions(Request $request, Role $role): JsonResponse
    {
        $allKeys = collect(config('permissions'))
            ->flatMap(fn ($module) => collect($module['perms'])->flatMap(fn ($group) => array_keys($group['items'])))
            ->all();

        $selected = collect($request->except(['_token']))
            ->filter() // checkbox present + truthy
            ->keys()
            ->intersect($allKeys) // only accept known permission keys, same guard as the old $default_privilage check
            ->map(fn ($key) => ['key' => $key])
            ->all();

        $before = $role->permissions()->pluck('key')->sort()->values()->all();

        $role->syncPermissions($selected);

        $after = collect($selected)->pluck('key')->sort()->values()->all();
        if ($before !== $after) {
            AuditLog::record('updated', $role, [
                'permissions' => ['old' => $before, 'new' => $after],
            ], __('دسترسی‌های نقش') . ": {$role->name}");
        }

        // Invalidate cached permission lists for every user holding this role.
        $role->users()->pluck('users.id')->each(fn ($id) => User::forgetPermissionCache($id));

        return response()->json([
            'success' => true,
            'message' => __('دسترسی ها با موفقیت بروز رسانی شد'),
        ]);
    }
}
