<?php

namespace App\Core\Audit\Observers;

use App\Core\Audit\Support\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        AuditRecorder::record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $original = array_intersect_key($model->getOriginal(), $changes);

        AuditRecorder::record($model, 'updated', $original, $changes);
    }

    public function deleted(Model $model): void
    {
        AuditRecorder::record($model, 'deleted', $model->getAttributes(), []);
    }
}
