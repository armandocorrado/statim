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
        if (! app()->bound('currentTenant')) {
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
            'tenant_id' => app('currentTenant')->id,
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
