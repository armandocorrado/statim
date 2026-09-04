<?php

namespace App\Core\Audit\Support;

use App\Core\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditRecorder
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(Model $model, string $action, array $old, array $new): void
    {
        // Prefer the model's own tenant_id (every Auditable model uses
        // BelongsToTenant) over the request-scoped `currentTenant` binding —
        // some auditable actions happen outside a tenant-identified request,
        // e.g. a guest accepting an invitation, where IdentifyTenant never
        // runs but the model itself still carries its tenant unambiguously.
        $tenantId = $model->getAttribute('tenant_id')
            ?? (app()->bound('currentTenant') ? app('currentTenant')->id : null);

        if (! $tenantId) {
            return;
        }

        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model::auditExcludedAttributes()
            : [];

        $old = Arr::except($old, $excluded);
        $new = Arr::except($new, $excluded);

        if ($action === 'updated' && empty($new)) {
            return;
        }

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
