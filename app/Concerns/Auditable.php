<?php

namespace App\Concerns;

use App\Models\AuditLog;

/**
 * Automatic CRUD audit logging for any Eloquent model.
 *
 * Usage - this is the entire integration cost for a new model:
 *
 *     class Product extends Model
 *     {
 *         use Auditable;
 *     }
 *
 * That's it. create()/update()/delete() (including via updateOrCreate(), fill()+save(), mass
 * assignment, etc.) are logged automatically with a field-level before/after diff, viewable at
 * /admin/audit-log. No controller changes needed for the basic case.
 *
 * What it can't see on its own: many-to-many sync() calls (roles/permissions assignment) don't
 * touch the owning model's attributes at all, so there's nothing here to dirty-track. Those are
 * logged with an explicit `AuditLog::record(...)` call at the point sync() happens - see
 * RoleController::savePermissions() and UserController::store() for examples.
 *
 * Customizing what gets logged, per model:
 *   protected array $auditExcept = ['some_noisy_column'];   // drop specific fields entirely
 *   protected array $auditRedact = ['ssn'];                 // keep the field (masked) but never
 *                                                            // store/show its actual value -
 *                                                            // 'password' is redacted this way
 *                                                            // by default already
 *   public function auditLabel(): string { return "..."; }  // custom human label instead of
 *                                                            // the name/title/key/username guess
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', $model->auditableChanges($model->getAttributes(), []));
        });

        static::updated(function ($model) {
            $changes = $model->auditableChanges($model->getChanges(), $model->getOriginal());
            if (!empty($changes)) {
                $model->writeAuditLog('updated', $changes);
            }
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', null);
        });

        // SoftDeletes-only: static::restored() doesn't exist unless the model uses that trait,
        // so this is guarded rather than unconditional.
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function ($model) {
                $model->writeAuditLog('restored', null);
            });
        }
    }

    protected function writeAuditLog(string $action, ?array $changes): void
    {
        AuditLog::record($action, $this, $changes);
    }

    /**
     * Builds the ['field' => ['old' => ..., 'new' => ...]] diff, dropping excluded/system fields
     * and masking redacted ones (present in the diff so "password was changed" is still
     * auditable, but the value itself is never stored or shown).
     */
    protected function auditableChanges(array $new, array $original): array
    {
        $except = array_merge(
            ['remember_token', 'api_token', 'created_at', 'updated_at', 'deleted_at'],
            $this->auditExcept ?? []
        );
        $redact = array_merge(['password'], $this->auditRedact ?? []);

        $changes = [];
        foreach ($new as $field => $value) {
            if (in_array($field, $except, true)) {
                continue;
            }

            if (in_array($field, $redact, true)) {
                $changes[$field] = [
                    'old' => array_key_exists($field, $original) && $original[$field] !== null ? '••••••••' : null,
                    'new' => $value !== null ? '••••••••' : null,
                ];
                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $value,
            ];
        }

        return $changes;
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}
