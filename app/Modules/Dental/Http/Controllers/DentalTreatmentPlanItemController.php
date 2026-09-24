<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Http\Requests\StoreDentalTreatmentPlanItemRequest;
use App\Modules\Dental\Http\Requests\UpdateDentalTreatmentPlanItemRequest;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;
use App\Modules\Dental\Models\DentalTreatmentPlanItemTooth;
use Illuminate\Http\RedirectResponse;

/**
 * Una voce per volta — a differenza del pattern "sostituisci in blocco"
 * usato altrove (BillingDocumentLine, QuoteLine): qui l'utente deve poter
 * modificare o cancellare una singola prestazione del piano senza
 * riscrivere/rischiare di alterare le altre. Vedi CLAUDE.md, "Preventivi
 * e piani di cura".
 */
class DentalTreatmentPlanItemController extends Controller
{
    public function store(StoreDentalTreatmentPlanItemRequest $request, Patient $patient, DentalTreatmentPlan $treatmentPlan): RedirectResponse
    {
        abort_if($treatmentPlan->patient_id !== $patient->id, 404);

        $data = $request->validated();

        $item = $treatmentPlan->items()->make([
            'service_catalog_item_id' => $data['service_catalog_item_id'],
            'quantity' => $data['quantity'],
            'notes' => $data['notes'] ?? null,
            'session_group' => $data['session_group'] ?? null,
            'sort_order' => $treatmentPlan->items()->count(),
        ]);
        $item->save();

        $this->syncTeeth($item, $data['teeth'] ?? []);

        return back()->with('success', 'Voce aggiunta al piano di cura.');
    }

    public function update(UpdateDentalTreatmentPlanItemRequest $request, Patient $patient, DentalTreatmentPlan $treatmentPlan, DentalTreatmentPlanItem $item): RedirectResponse
    {
        abort_if($treatmentPlan->patient_id !== $patient->id || $item->treatment_plan_id !== $treatmentPlan->id, 404);

        $data = $request->validated();

        $item->update([
            'service_catalog_item_id' => $data['service_catalog_item_id'],
            'quantity' => $data['quantity'],
            'notes' => $data['notes'] ?? null,
            'session_group' => $data['session_group'] ?? null,
        ]);

        $item->teeth()->delete();
        $this->syncTeeth($item, $data['teeth'] ?? []);

        return back()->with('success', 'Voce aggiornata.');
    }

    public function destroy(Patient $patient, DentalTreatmentPlan $treatmentPlan, DentalTreatmentPlanItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        abort_if($treatmentPlan->patient_id !== $patient->id || $item->treatment_plan_id !== $treatmentPlan->id, 404);

        // cascadeOnDelete su dental_treatment_plan_item_teeth ripulisce i
        // denti collegati automaticamente.
        $item->delete();

        return back()->with('success', 'Voce eliminata dal piano di cura.');
    }

    /**
     * @param  list<string>  $teeth
     */
    private function syncTeeth(DentalTreatmentPlanItem $item, array $teeth): void
    {
        foreach (array_unique($teeth) as $toothNumber) {
            $tooth = new DentalTreatmentPlanItemTooth(['tooth_number' => $toothNumber]);
            $tooth->treatment_plan_item_id = $item->id;
            $tooth->save();
        }
    }
}
