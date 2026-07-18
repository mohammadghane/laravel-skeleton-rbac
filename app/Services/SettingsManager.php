<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

/**
 * Key-value settings store. Keys are always "category:item" (e.g. "general:site_title"),
 * matching the group/item structure in config/settings.php.
 *
 * A setting item's `has_pattern` flag (defined per-item in config/settings.php) decides
 * whether it's scoped to a center/user or stored as a single global row - ported as-is
 * from the old CI Settings::save().
 */
class SettingsManager
{
    /** Single row lookup, same signature as the old Settings::fetch(). */
    public function fetch(array $params = []): ?Setting
    {
        return Setting::query()
            ->when(array_key_exists('center_id', $params), fn ($q) => $q->where('center_id', $params['center_id']))
            ->when(array_key_exists('user_id', $params), fn ($q) => $q->where('user_id', $params['user_id']))
            ->when(isset($params['key']), fn ($q) => $q->where('key', $params['key']))
            ->first();
    }

    /** Raw value for a single key, ignoring scope - same as the old Settings::fetchItem(). */
    public function fetchItem(string $key): mixed
    {
        return Setting::where('key', $key)->value('value');
    }

    /** All rows matching $params, keyed by their `key` column - same as the old Settings::fetchAll(). */
    public function fetchAll(array $params = []): array
    {
        $query = Setting::query();
        foreach ($params as $column => $value) {
            $query->where($column, $value);
        }

        return $query->get()->keyBy('key')->all();
    }

    public function delete(array $params = []): bool
    {
        $query = Setting::query();
        foreach ($params as $column => $value) {
            $query->where($column, $value);
        }

        // Deliberately per-row, not a single mass DELETE - a query-builder ->delete() call
        // bypasses Eloquent model events entirely, which would silently break audit logging
        // for this path (see App\Concerns\Auditable).
        $deleted = false;
        foreach ($query->get() as $setting) {
            $setting->delete();
            $deleted = true;
        }

        return $deleted;
    }

    /**
     * Persist a posted settings form. $data is the raw request input (["category:item" => value, ...],
     * with a "category:item:remove" pseudo-key meaning "delete this setting"). $configItems is
     * config('settings') (or the relevant slice of it) so we can read each item's `has_pattern` flag
     * and default_value for the diff.
     */
    public function save(array $data, array $configItems, ?int $centerId = null, ?int $userId = null): bool
    {
        $centerId ??= Auth::user()?->center_id;
        $userId ??= Auth::id();

        $currentSettings = $this->fetchAll([
            'center_id' => $centerId,
            'user_id' => $userId,
        ]);

        foreach (array_keys($data) as $item) {
            $parts = explode(':', $item);
            $category = $parts[0] ?? null;
            $key = $parts[1] ?? null;
            $remove = $parts[2] ?? '';

            if ($category === null || $key === null) {
                continue;
            }

            $hasPattern = (bool) ($configItems[$category]['items'][$key]['has_pattern'] ?? false);

            $searchParams = ['key' => "{$category}:{$key}"];
            if ($hasPattern) {
                $searchParams['center_id'] = $centerId;
                $searchParams['user_id'] = $userId;
            }

            $current = $this->fetch($searchParams);

            if ($remove !== '') {
                $this->delete(['key' => "{$category}:{$key}"]);
                continue;
            }

            $shouldWrite = false;
            if (isset($currentSettings[$item])) {
                $shouldWrite = $currentSettings[$item]->value !== $this->normalize($data[$item]);
            } else {
                $default = $configItems[$category]['items'][$key]['default_value'] ?? null;
                $shouldWrite = $this->normalize($data[$item]) !== $this->normalize($default);
            }

            if (!$shouldWrite) {
                continue;
            }

            $payload = [
                'key' => $item,
                'value' => $this->normalize($data[$item]),
                'user_id' => $userId,
            ];
            if ($hasPattern) {
                $payload['center_id'] = $centerId;
            }

            if ($current) {
                $current->update($payload);
            } else {
                Setting::create($payload);
            }
        }

        return true;
    }

    private function normalize(mixed $value): ?string
    {
        return is_array($value) ? json_encode($value) : (is_null($value) ? null : (string) $value);
    }

    /**
     * Convenience reader for app code: setting_manager()->value('general:site_title').
     * Falls back center -> global -> $default, since a lot of settings only have a global row.
     */
    public function value(string $key, ?int $centerId = null, mixed $default = null): mixed
    {
        $centerId ??= Auth::user()?->center_id;

        $scoped = Setting::where('key', $key)->where('center_id', $centerId)->value('value');
        if ($scoped !== null) {
            return $scoped;
        }

        return Setting::where('key', $key)->whereNull('center_id')->value('value') ?? $default;
    }
}
