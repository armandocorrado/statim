<?php

namespace App\Core\Quotes\Policies;

use App\Core\Quotes\Models\Quote;
use App\Models\User;

/**
 * Tre livelli distinti, non annidati l'uno nell'altro:
 * 1. Visualizzazione (prezzi inclusi) — treatment_plans.view, per-ruolo
 *    (admin/segreteria/odontoiatra/igienista, già a catalogo).
 * 2. Modifica prezzi in bozza — treatment_plans.administer (per-ruolo,
 *    admin/segreteria) OPPURE treatment_plans.prices.edit, un permesso
 *    concesso DIRETTAMENTE al singolo utente dall'admin studio (mai a un
 *    ruolo — vedi UserController::updateQuotePricePermission()), sopra
 *    il ruolo fisso odontoiatra/igienista. Usa lo stesso meccanismo team
 *    -scoped di spatie/laravel-permission già usato per i ruoli
 *    (model_has_permissions ha la stessa colonna tenant_id di
 *    model_has_roles), nessuna migration nuova.
 * 3. Emissione/gestione (issue, transizioni di stato) — SOLO
 *    treatment_plans.administer, mai treatment_plans.prices.edit.
 *
 * Punto critico: issue()/delete()/transition() NON devono mai delegare a
 * update() (lo facevano prima di questo permesso) — altrimenti un
 * dentista abilitato a modificare i prezzi erediterebbe anche il potere
 * di emettere/cancellare il preventivo, violando il livello 3.
 */
class QuotePolicy
{
    /**
     * Mai assegnato a un ruolo di default — solo concesso direttamente a
     * un singolo utente dall'admin studio. Vedi RoleGovernanceTest per il
     * test di regressione che verifica non compaia mai nel catalogo
     * ruoli.
     */
    const PRICES_EDIT_PERMISSION = 'treatment_plans.prices.edit';

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
            && $quote->isDraft()
            && ($user->can('treatment_plans.administer') || $user->can(self::PRICES_EDIT_PERMISSION));
    }

    public function issue(User $user, Quote $quote): bool
    {
        return $quote->tenant_id === $user->tenant_id
            && $quote->isDraft()
            && $user->can('treatment_plans.administer');
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $quote->tenant_id === $user->tenant_id
            && $quote->isDraft()
            && $user->can('treatment_plans.administer');
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
