<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Audit\Support\AuditRecorder;
use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Enums\ToothCondition;
use App\Modules\Dental\Http\Requests\StoreDentalToothConditionRequest;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Models\DentalDocumentTooth;
use App\Modules\Dental\Models\DentalToothCondition;
use App\Modules\Dental\Support\FdiToothNumbers;
use App\Modules\Dental\Support\ToothConditionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DentalOdontogramController extends Controller
{
    /**
     * Come DentalClinicalRecordController::show: un'unica voce di audit
     * per apertura pagina, non una per dente.
     */
    public function show(Request $request, Patient $patient): Response
    {
        $this->authorize('viewOdontogram', [DentalToothCondition::class, $patient]);

        $user = $request->user();
        // Chi apre l'odontogramma ha oggi sempre clinical_records.view
        // (solo admin/odontoiatra hanno odontogram.view) — questo filtro
        // è comunque applicato per difesa in profondità, stessa logica di
        // DentalClinicalRecordController::show(), così il collegamento
        // dente↔documento non apre un varco se il catalogo RBAC cambiasse.
        $hasFullAccess = $user->can('clinical_records.view');

        $currentStates = ToothConditionResolver::currentStates($patient->id);

        $documentsByTooth = DentalDocumentTooth::query()
            ->whereHas('document', fn ($query) => $query->where('patient_id', $patient->id))
            ->with('document:id,section,document_type,description,original_filename,mime_type,created_at')
            ->when(
                ! $hasFullAccess,
                fn ($query) => $query->whereHas(
                    'document',
                    fn ($q) => $q->where('section', DentalRecordSection::Hygiene->value),
                ),
            )
            ->get()
            ->groupBy('tooth_number')
            ->map(fn ($rows) => $rows->pluck('document')->values());

        $history = DentalToothCondition::query()
            ->where('patient_id', $patient->id)
            ->with(['operator:id,name', 'diaryEntry:id,entry_date'])
            ->orderByDesc('recorded_date')
            ->orderByDesc('id')
            ->get();

        AuditRecorder::record($patient, 'odontogram_viewed', [], []);

        return Inertia::render('Dental/Odontogram', [
            'patient' => $patient->only(['id', 'first_name', 'last_name']),
            'permanentTeeth' => FdiToothNumbers::permanent(),
            'deciduousTeeth' => FdiToothNumbers::deciduous(),
            'currentStates' => $currentStates->map->only(['id', 'tooth_number', 'condition_type', 'recorded_date']),
            'history' => $history,
            'documentsByTooth' => $documentsByTooth,
            'conditionOptions' => array_map(
                fn (ToothCondition $case) => ['value' => $case->value, 'label' => $case->label()],
                ToothCondition::cases(),
            ),
            'diaryEntries' => DentalDiaryEntry::where('patient_id', $patient->id)
                ->orderByDesc('entry_date')
                ->get(['id', 'entry_date', 'section']),
            'canManage' => $user->can('odontogram.update'),
        ]);
    }

    public function store(StoreDentalToothConditionRequest $request, Patient $patient): RedirectResponse
    {
        $condition = new DentalToothCondition($request->validated());
        $condition->patient_id = $patient->id;
        $condition->operator_id = $request->user()->id;
        $condition->save();

        return back()->with('success', 'Stato del dente registrato.');
    }
}
