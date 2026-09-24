<?php

namespace App\Core\Users\Models;

use App\Core\Tenancy\Concerns\UsesTenantConnection;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Vedi App\Core\Users\Models\Role per il perche' di questa sottoclasse.
 */
class Permission extends SpatiePermission
{
    use UsesTenantConnection;
}
