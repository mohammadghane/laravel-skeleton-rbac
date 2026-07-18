<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'auditable_type', 'auditable_id', 'action', 'label', 'changes',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Write one audit entry. Used both by the Auditable trait (automatic create/update/delete
     * on any model that uses it) and by call sites that need to log something a model event
     * can't see on its own - e.g. a many-to-many sync() (roles/permissions), which doesn't
     * dirty-track the owning model at all.
     *
     * $changes shape: ['field' => ['old' => ..., 'new' => ...], ...]. Pass null/[] for actions
     * with nothing to diff (e.g. delete) - a null `changes` column just doesn't render a diff.
     */
    public static function record(string $action, Model $auditable, ?array $changes, ?string $label = null): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'label' => $label ?? self::defaultLabel($auditable),
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    protected static function defaultLabel(Model $model): string
    {
        if (method_exists($model, 'auditLabel')) {
            return $model->auditLabel();
        }

        foreach (['name', 'title', 'key', 'username'] as $attribute) {
            if (!empty($model->{$attribute})) {
                return (string) $model->{$attribute};
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }
}
