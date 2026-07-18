<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $currentUser = $request->user();

        $users = User::query()
            ->when($currentUser->is_agency, fn ($q) => $q->where('agency_id', $currentUser->id))
            ->when(!$currentUser->isAdmin(), fn ($q) => $q->where('center_id', $currentUser->center_id))
            ->when($request->filled('filters.gender'), fn ($q) => $q->where('gender', $request->input('filters.gender')))
            ->when($request->filled('filters.city'), fn ($q) => $q->where('city', $request->input('filters.city')))
            ->orderByDesc('id')
            ->paginate(config('app.page_size', 20))
            ->withQueryString();

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.users.partials.table', compact('users'));
        }

        return view('admin.users.index', compact('users'));
    }

    public function profile(Request $request): View
    {
        $user = $request->user()->load('roles');
        $data = ['user' => $user, 'roles' => Role::all(), 'editingSelf' => true];

        if ($request->ajax()) {
            return view('admin.users.form', $data);
        }

        return view('admin.modal-page', [
            'title' => __('پروفایل کاربری'),
            'view' => 'admin.users.form',
            'data' => $data,
        ]);
    }

    /**
     * Ajax username/email/cell/national_code uniqueness probe.
     * Old route: UserController::isValid()
     */
    public function checkUnique(Request $request): JsonResponse
    {
        $field = $request->input('field'); // one of: username, email, cell, national_code
        $value = $request->input('value');
        $ignoreId = $request->input('id');

        if (!in_array($field, ['username', 'email', 'cell', 'national_code'], true) || $value === null) {
            return response()->json(['valid' => false]);
        }

        $exists = User::where($field, $value)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        return response()->json(['valid' => !$exists]);
    }

    public function edit(?User $user, Request $request): View
    {
        $currentUser = $request->user();

        if ($user && $currentUser->is_agency && $user->agency_id !== $currentUser->id) {
            abort(404);
        }

        $data = [
            'user' => $user?->load('roles'),
            'roles' => Role::all(),
            'currentRoleIds' => $user?->roles->pluck('id')->all() ?? [],
        ];

        if ($request->ajax()) {
            return view('admin.users.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $user ? __('ویرایش کاربر') : __('کاربر جدید'),
            'view' => 'admin.users.form',
            'data' => $data,
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $data = $request->validated();

        // Non-admins may only ever edit themselves (mirrors old `!isAllow('system-manager')` guard).
        if (!$currentUser->hasPermission('admin/users')) {
            $data['id'] = $currentUser->id;
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($currentUser->is_agency) {
            if (!empty($data['id'])) {
                $owned = User::where('id', $data['id'])->where('agency_id', $currentUser->id)->exists();
                abort_unless($owned, 404);
            }
            $data['agency_id'] = $currentUser->id;
        }

        $roles = $currentUser->hasPermission('admin/users')
            ? ($data['roles'] ?? [Role::where('name', 'user')->value('id')])
            : null;
        unset($data['roles']);

        $isNew = empty($data['id']);
        if ($isNew) {
            $data['setter'] = $currentUser->id;
            $data['set_with'] = $request->ip();
        }

        $user = User::updateOrCreate(['id' => $data['id'] ?? null], $data);

        if ($isNew) {
            $user->sana_code = random_int(101, 999) . 'C' . $user->id;
            $user->save();
        }

        $before = $isNew ? [] : $user->roles()->pluck('name')->sort()->values()->all();

        if ($roles !== null) {
            $user->roles()->sync($roles);
            User::forgetPermissionCache($user->id);

            $after = Role::whereIn('id', $roles)->pluck('name')->sort()->values()->all();
            if ($before !== $after) {
                AuditLog::record('updated', $user, [
                    'roles' => ['old' => $before, 'new' => $after],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('اطلاعات کاربر با موفقیت به ثبت رسید'),
        ]);
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if ($currentUser->is_agency && $user->agency_id !== $currentUser->id) {
            abort(404);
        }

        $user->delete(); // soft delete

        return response()->json(['success' => true]);
    }
}
