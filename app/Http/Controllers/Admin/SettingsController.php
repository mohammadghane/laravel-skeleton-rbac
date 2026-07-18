<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsManager $settings)
    {
    }

    /**
     * Self-service settings for the current user's own center.
     */
    public function edit(Request $request)
    {
        $groups = $this->resolveGroups();
        $groups = $this->applyCurrentValues($groups, $request->user()->center_id, $request->user()->id);

        if ($request->ajax()) {
            return view('admin.settings.form', ['groups' => $groups, 'action' => route('admin.settings.store')]);
        }

        return view('admin.modal-page', [
            'title' => __('تنظیمات'),
            'view' => 'admin.settings.form',
            'data' => ['groups' => $groups, 'action' => route('admin.settings.store')],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $configItems = config('settings');
        $data = $request->except(['_token']);

        $ok = $this->settings->save($data, $configItems, $request->user()->center_id, $request->user()->id);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? __('تغییرات با موفقیت ذخیره شد') : __('خطایی در انجام عملیات رخ داده است'),
        ]);
    }

    /**
     * Privileged full page for editing another center's settings (every group, not just
     * 'general'). Old centerSettings($center_id) - gate this route with
     * ->middleware('permission:admin/settings') in routes/settings.php.
     *
     * NOTE: takes a plain center id rather than a bound Center model, since no Center model
     * shipped with this ACL package - swap the int param for `Center $center` once you wire
     * one up, same as the old shop/Center model this action originally depended on.
     */
    public function centerEdit(int $centerId, Request $request): View
    {
        $groups = $this->resolveGroups();
        $groups = $this->applyCurrentValues($groups, $centerId, $request->user()->id);

        return view('admin.modal-page', [
            'title' => __('تنظیمات مرکز'),
            'view' => 'admin.settings.form',
            'data' => ['groups' => $groups, 'action' => route('admin.settings.center.store', $centerId)],
        ]);
    }

    public function centerStore(int $centerId, Request $request): JsonResponse
    {
        $configItems = config('settings');
        $data = $request->except(['_token']);

        $ok = $this->settings->save($data, $configItems, $centerId, $request->user()->id);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? __('تغییرات با موفقیت ذخیره شد') : __('خطایی در انجام عملیات رخ داده است'),
        ]);
    }

    /** @param (callable(array):bool)|null $filter */
    private function resolveGroups(?callable $filter = null): array
    {
        $groups = config('settings');

        foreach ($groups as $groupKey => $group) {
            if ($filter && !$filter($group)) {
                unset($groups[$groupKey]);
                continue;
            }

            foreach ($group['items'] as $itemKey => $item) {
                if (isset($item['privilage']) && !auth()->user()?->hasPermission($item['privilage'])) {
                    unset($groups[$groupKey]['items'][$itemKey]);
                    continue;
                }

                // Closure-based option providers (replaces the old
                // options['#type']=='function' + provider-method indirection).
                if (isset($item['options']) && is_callable($item['options'])) {
                    $groups[$groupKey]['items'][$itemKey]['options'] = ($item['options'])();
                }
            }
        }

        return $groups;
    }

    private function applyCurrentValues(array $groups, ?int $centerId, ?int $userId): array
    {
        // Deliberately NOT filtering by user_id here - the old controller displayed every
        // setting for the center regardless of who last saved it. user_id only matters
        // inside SettingsManager::save() when deciding update-in-place vs insert-new.
        $current = $this->settings->fetchAll(['center_id' => $centerId]);

        foreach ($current as $key => $setting) {
            [$category, $item] = array_pad(explode(':', $key, 2), 2, null);
            if ($category === null || $item === null || !isset($groups[$category]['items'][$item])) {
                continue;
            }

            $value = $setting->value;

            // Array-shaped items (e.g. from_to) are stored JSON-encoded - decode back to an
            // array for the view, otherwise `is_array($item['default_value'])` in the Blade
            // template fails and the field silently redisplays empty even though it saved fine.
            if (is_array($groups[$category]['items'][$item]['default_value'] ?? null)) {
                $decoded = json_decode((string) $value, true);
                $value = is_array($decoded) ? $decoded : $value;
            }

            $groups[$category]['items'][$item]['default_value'] = $value;
        }

        return $groups;
    }
}
