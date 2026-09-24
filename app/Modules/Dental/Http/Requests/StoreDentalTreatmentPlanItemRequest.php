<?php

namespace App\Modules\Dental\Http\Requests;

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;
use App\Modules\Dental\Support\FdiToothNumbers;
use App\Modules\Dental\Support\TreatmentPlanAccessChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDentalTreatmentPlanItemRequest extends FormRequest
{
    /**
     * Il controllo grezzo (createFor) verifica che l'utente abbia un
     * permesso qualunque di gestione piano; qui si aggiunge il controllo
     * fine sulla categoria della voce che si sta per creare — un
     * igienista non deve poter creare una voce di categoria diversa da
     * Hygiene, anche se ha "un" permesso di gestione piano.
     */
    public function authorize(): bool
    {
        if (! $this->user()->can('createFor', [DentalTreatmentPlanItem::class, $this->route('treatmentPlan')])) {
            return false;
        }

        $category = ServiceCatalogItem::find($this->input('service_catalog_item_id'))?->category;

        return TreatmentPlanAccessChecker::canManageItem($this->user(), $category);
    }

    public function rules(): array
    {
        return [
            'service_catalog_item_id' => [
                'required', 'ulid',
                Rule::exists('service_catalog_items', 'id'),
            ],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'session_group' => ['nullable', 'string', 'max:100'],
            'teeth' => ['nullable', 'array'],
            'teeth.*' => ['string', Rule::in(FdiToothNumbers::all())],
        ];
    }
}
