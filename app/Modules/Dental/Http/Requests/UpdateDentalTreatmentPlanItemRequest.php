<?php

namespace App\Modules\Dental\Http\Requests;

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Modules\Dental\Support\FdiToothNumbers;
use App\Modules\Dental\Support\TreatmentPlanAccessChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDentalTreatmentPlanItemRequest extends FormRequest
{
    /**
     * Il controllo fine è sulla categoria RISULTANTE (quella sottomessa),
     * non su quella attuale della voce — copre anche il caso in cui si
     * stia cambiando la prestazione collegata, non solo quantità/denti.
     */
    public function authorize(): bool
    {
        if (! $this->user()->can('update', $this->route('item'))) {
            return false;
        }

        $category = ServiceCatalogItem::find($this->input('service_catalog_item_id'))?->category;

        return TreatmentPlanAccessChecker::canManageItem($this->user(), $category);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'service_catalog_item_id' => [
                'required', 'ulid',
                Rule::exists('service_catalog_items', 'id')->where('tenant_id', $tenantId),
            ],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'session_group' => ['nullable', 'string', 'max:100'],
            'teeth' => ['nullable', 'array'],
            'teeth.*' => ['string', Rule::in(FdiToothNumbers::all())],
        ];
    }
}
