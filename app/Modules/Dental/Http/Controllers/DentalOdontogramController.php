<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Audit\Support\AuditRecorder;
use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Enums\ToothCondition;
use App\Modules\Dental\Http\Requests\StoreDentalToothConditionRequest;
use App\Modules\Dental\Models\DentalDiaryEntry;
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

        $currentStates = ToothConditionResolver::currentStates($patient->id);

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
            'conditionOptions' => array_map(
                fn (ToothCondition $case) => ['value' => $case->value, 'label' => $case->label()],
                ToothCondition::cases(),
            ),
            'diaryEntries' => DentalDiaryEntry::where('patient_id', $patient->id)
                ->orderByDesc('entry_date')
                ->get(['id', 'entry_date', 'section']),
            'canManage' => $request->user()->can('odontogram.update'),
        ]);
    }

    public function store(StoreDentalToothConditionRequest $request, Patient $patient): RedirectResponse
    {
        $condition = new DentalToothCondition($request->validated());
        $condition->patient_id = $patient->id;
        $condition->tenant_id = $patient->tenant_id;
        $condition->operator_id = $request->user()->id;
        $condition->save();

        return back()->with('success', 'Stato del dente registrato.');
    }
}
