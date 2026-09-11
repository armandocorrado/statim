<?php

namespace App\Core\Quotes\Policies;

use App\Core\Quotes\Models\Quote;
use App\Models\User;

/**
 * Stessa forma di BillingDocumentPolicy: viewAny/view su
 * treatment_plans.view (letto anche dai ruoli clinici, che devono poter
 * vedere il preventivo generato dal loro piano), ogni scrittura su
 * treatment_plans.administer — il preventivo è amministrativo end-to-end,
 * i ruoli clinici non ne gestiscono mai il ciclo di vita commerciale.
 */
class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('treatment_plans.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $quote->tenant_id === $user->tenant_id && $user->can('treatment_plans.view');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $quote->tenant_id === $user->tenant_id
            && $user->can('treatment_plans.administer')
            && $quote->isDraft();
    }

    public function issue(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote);
    }

    /**
     * Ogni transizione dopo Issued (accettato/rifiutato/in corso/
     * completato) passa da qui — la validità della transizione stessa è
     * verificata da QuoteTransitions, non da questa Policy.
     */
    public function transition(User $user, Quote $quote): bool
    {
        return $quote->tenant_id === $user->tenant_id
            && $user->can('treatment_plans.administer')
            && ! $quote->isDraft();
    }
}
