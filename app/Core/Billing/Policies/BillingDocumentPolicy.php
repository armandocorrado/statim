<?php

namespace App\Core\Billing\Policies;

use App\Core\Billing\Models\BillingDocument;
use App\Models\User;

class BillingDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.view');
    }

    public function view(User $user, BillingDocument $document): bool
    {
        return $user->can('billing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('billing.manage');
    }

    public function update(User $user, BillingDocument $document): bool
    {
        return $user->can('billing.manage') && $document->isDraft();
    }

    public function issue(User $user, BillingDocument $document): bool
    {
        return $user->can('billing.manage') && $document->isDraft();
    }

    public function delete(User $user, BillingDocument $document): bool
    {
        return $user->can('billing.manage') && $document->isDraft();
    }
}
