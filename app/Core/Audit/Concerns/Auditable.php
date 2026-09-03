<?php

namespace App\Core\Audit\Concerns;

use App\Core\Audit\Observers\AuditObserver;

trait Auditable
{
    public static function bootAuditable(): void
    {
        // static::observe() instantiates `new static`, so it can't run
        // synchronously from within boot() (the model isn't done booting
        // yet) — defer it until the boot cycle for this model completes.
        static::whenBooted(fn () => static::observe(AuditObserver::class));
    }
}
