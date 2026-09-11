<?php

namespace App\Modules\Dental\Http\Requests;

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Modules\Dental\Support\FdiToothNumbers;
use App\Modules\Dental\Support\TreatmentPlanAccessChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDentalTreatmentPlanItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('treatmentPlan'));
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'items' => ['present', 'array'],
            'items.*.service_catalog_item_id' => [
                'required', 'ulid',
                Rule::exists('service_catalog_items', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'items.*.session_group' => ['nullable', 'string', 'max:100'],
            'items.*.teeth' => ['nullable', 'array'],
            'items.*.teeth.*' => ['string', Rule::in(FdiToothNumbers::all())],
        ];
    }

    /**
     * Verifica che OGNI voce inviata sia di una categoria che questo
     * utente può gestire — chi ha solo treatment_plans.hygiene.manage non
     * può infilare (né lasciare invariata) una voce di categoria diversa
     * da Hygiene, anche se la richiesta nel suo complesso è autorizzata
     * a livello di piano.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            $catalogIds = array_filter(array_column($items, 'service_catalog_item_id'));
            $categories = ServiceCatalogItem::query()
                ->whereIn('id', $catalogIds)
                ->pluck('category', 'id');

            foreach ($items as $index => $item) {
                $catalogId = $item['service_catalog_item_id'] ?? null;
                $category = $catalogId ? ($categories[$catalogId] ?? null) : null;

                if (! TreatmentPlanAccessChecker::canManageItem($this->user(), $category)) {
                    $validator->errors()->add(
                        "items.{$index}.service_catalog_item_id",
                        'Non hai i permessi per gestire questa voce del piano.',
                    );
                }
            }
        });
    }
}
