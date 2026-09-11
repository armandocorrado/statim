<?php

namespace App\Core\Quotes\Models;

use App\Core\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ServiceCatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Listino prestazioni per-tenant — profession-agnostic, come
 * AppointmentType. `category` è testo libero non interpretato qui: un
 * verticale può usarlo per le proprie regole di accesso (vedi
 * App\Modules\Dental\Support\TreatmentPlanAccessChecker) senza che questo
 * model ne sappia nulla. Nessuna rotta di delete: una voce si disattiva
 * (`is_active = false`), non si cancella — un piano di cura passato
 * potrebbe ancora referenziarla.
 */
// tenant_id è mass-assignable (a differenza degli altri model tenant-scoped
// di questo progetto) perché DentalServiceCatalogProvisioner lo popola via
// firstOrCreate() fuori dal ciclo HTTP — stesso motivo per cui
// AppointmentType lo include, vedi App\Core\Agenda\Models\AppointmentType.
#[Fillable(['tenant_id', 'name', 'description', 'category', 'base_price', 'default_vat_rate', 'default_vat_exemption_reason', 'default_duration_minutes', 'is_active'])]
class ServiceCatalogItem extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return ServiceCatalogItemFactory::new();
    }

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'default_vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
