<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Audit\Support\AuditRecorder;
use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalAlert;
use App\Modules\Dental\Models\DentalAnamnesis;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Support\FdiToothNumbers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DentalClinicalRecordController extends Controller
{
    /**
     * Apre la cartella clinica di un paziente — anamnesi, alert attivi,
     * diario e documenti (filtrati per sezione se l'utente ha solo
     * l'accesso "igiene"). Ogni apertura è un accesso GDPR-rilevante:
     * una sola voce di audit per l'intera pagina, non una per sotto-
     * risorsa — altrimenti diventa rumore, non tracciabilità utile.
     */
    public function show(Request $request, Patient $patient): Response
    {
        $this->authorize('view-clinical-record', $patient);

        $user = $request->user();
        $hasFullAccess = $user->can('clinical_records.view');

        $diaryEntries = DentalDiaryEntry::query()
            ->where('patient_id', $patient->id)
            ->with('operator:id,name')
            ->when(! $hasFullAccess, fn ($query) => $query->where('section', DentalRecordSection::Hygiene->value))
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->get();

        $documents = DentalDocument::query()
            ->where('patient_id', $patient->id)
            ->with('uploader:id,name', 'teeth:id,document_id,tooth_number')
            ->when(! $hasFullAccess, fn ($query) => $query->where('section', DentalRecordSection::Hygiene->value))
            ->orderByDesc('created_at')
            ->get();

        AuditRecorder::record($patient, 'clinical_record_viewed', [], []);

        return Inertia::render('Dental/ClinicalRecord', [
            'patient' => $patient->only(['id', 'first_name', 'last_name']),
            'anamnesis' => DentalAnamnesis::where('patient_id', $patient->id)->first(),
            'alerts' => DentalAlert::where('patient_id', $patient->id)->where('is_active', true)->get(),
            'diaryEntries' => $diaryEntries,
            'documents' => $documents,
            'permanentTeeth' => FdiToothNumbers::permanent(),
            'deciduousTeeth' => FdiToothNumbers::deciduous(),
            'hasFullAccess' => $hasFullAccess,
            'canManage' => $user->can('clinical_records.update') || $user->can('clinical_records.hygiene.update'),
        ]);
    }
}
