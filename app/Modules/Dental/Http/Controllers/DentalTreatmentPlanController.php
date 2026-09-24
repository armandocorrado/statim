<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Models\Quote;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Http\Requests\StoreDentalTreatmentPlanRequest;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Support\FdiToothNumbers;
use App\Modules\Dental\Support\TreatmentPlanAccessChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DentalTreatmentPlanController extends Controller
{
    public function index(Request $request, Patient $patient): Response
    {
        $this->authorize('viewAny', [DentalTreatmentPlan::class, $patient]);

        return Inertia::render('Dental/TreatmentPlans/Index', [
            'patient' => $patient->only(['id', 'first_name', 'last_name']),
            'plans' => DentalTreatmentPlan::where('patient_id', $patient->id)
                ->withCount('items')
                ->orderByDesc('created_at')
                ->get(),
            'canCreate' => $request->user()->can('createFor', [DentalTreatmentPlan::class, $patient]),
        ]);
    }

    public function store(StoreDentalTreatmentPlanRequest $request, Patient $patient): RedirectResponse
    {
        $plan = new DentalTreatmentPlan($request->validated());
        $plan->patient_id = $patient->id;
        $plan->created_by = $request->user()->id;
        $plan->save();

        return to_route('dental.treatment-plans.show', [$patient, $plan])->with('success', 'Piano di cura creato.');
    }

    public function show(Request $request, Patient $patient, DentalTreatmentPlan $treatmentPlan): Response
    {
        $this->authorize('view', $treatmentPlan);

        abort_if($treatmentPlan->patient_id !== $patient->id, 404);

        $user = $request->user();

        return Inertia::render('Dental/TreatmentPlans/Show', [
            'patient' => $patient->only(['id', 'first_name', 'last_name']),
            'plan' => $treatmentPlan->load(['items.serviceCatalogItem', 'items.teeth', 'creator:id,name']),
            'quotes' => $treatmentPlan->quotes()->orderByDesc('created_at')->get(['id', 'status', 'total_amount', 'created_at']),
            'serviceCatalogItems' => ServiceCatalogItem::where('is_active', true)->orderBy('name')->get(),
            'permanentTeeth' => FdiToothNumbers::permanent(),
            'deciduousTeeth' => FdiToothNumbers::deciduous(),
            'canManage' => TreatmentPlanAccessChecker::canManageAny($user),
            'canManageHygieneOnly' => ! $user->can('treatment_plans.clinical.manage') && $user->can('treatment_plans.hygiene.manage'),
            'canGenerateQuote' => $user->can('generateQuote', $treatmentPlan),
        ]);
    }

    public function destroy(Patient $patient, DentalTreatmentPlan $treatmentPlan): RedirectResponse
    {
        $this->authorize('delete', $treatmentPlan);

        abort_if($treatmentPlan->patient_id !== $patient->id, 404);

        $treatmentPlan->delete();

        return to_route('dental.treatment-plans.index', $patient)->with('success', 'Piano di cura eliminato.');
    }

    /**
     * Compone in Dental (che conosce i denti) il testo congelato di ogni
     * riga prima di passarlo a Core, che non deve mai interpretare un
     * numero di dente — vedi CLAUDE.md, "Preventivi e piani di cura".
     */
    public function generateQuote(Request $request, Patient $patient, DentalTreatmentPlan $treatmentPlan): RedirectResponse
    {
        $this->authorize('generateQuote', $treatmentPlan);

        abort_if($treatmentPlan->patient_id !== $patient->id, 404);

        $quote = new Quote(['patient_id' => $patient->id, 'source_treatment_plan_id' => $treatmentPlan->id]);
        $quote->status = QuoteStatus::Draft;
        $quote->created_by = $request->user()->id;
        $quote->save();

        foreach ($treatmentPlan->items()->with(['serviceCatalogItem', 'teeth'])->get() as $index => $item) {
            $catalogItem = $item->serviceCatalogItem;
            $teeth = $item->teeth->pluck('tooth_number');
            $description = $catalogItem->name.($teeth->isNotEmpty() ? ' — dente '.$teeth->implode(', ') : '');

            $line = $quote->lines()->make([
                'service_catalog_item_id' => $catalogItem->id,
                'source_treatment_plan_item_id' => $item->id,
                'description' => $description,
                'quantity' => $item->quantity,
                'unit_price' => $catalogItem->base_price,
                'discount_percent' => null,
                'vat_rate' => $catalogItem->default_vat_rate,
                'vat_exemption_reason' => $catalogItem->default_vat_exemption_reason,
                'sort_order' => $index,
            ]);
            $line->line_total = round((float) $item->quantity * (float) $catalogItem->base_price, 2);
            $line->save();
        }

        return to_route('quotes.show', $quote)->with('success', 'Preventivo generato dal piano di cura.');
    }
}
