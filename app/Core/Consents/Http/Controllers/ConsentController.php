<?php

namespace App\Core\Consents\Http\Controllers;

use App\Core\Audit\Support\AuditRecorder;
use App\Core\Consents\Http\Requests\StoreConsentRequest;
use App\Core\Consents\Models\Consent;
use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function store(StoreConsentRequest $request, Patient $patient): RedirectResponse
    {
        $consent = new Consent($request->validated());
        $consent->patient_id = $patient->id;
        // Il consenso per un minore è espresso dal tutore registrato — non
        // per un adulto con un guardian_patient_id valorizzato per altri
        // motivi (es. intestatario fattura): vedi Patient::isMinor().
        $consent->given_by_patient_id = $patient->isMinor() ? $patient->guardian_patient_id : null;
        $consent->recorded_by = $request->user()->id;
        $consent->save();

        AuditRecorder::record($consent, 'consent_granted', [], $consent->getAttributes());

        return back()->with('success', 'Consenso registrato.');
    }

    public function revoke(Request $request, Patient $patient, Consent $consent): RedirectResponse
    {
        $this->authorize('revoke', $consent);

        abort_if($consent->patient_id !== $patient->id, 404);

        $oldValues = ['revoked_at' => $consent->revoked_at];
        $consent->revoked_at = now();
        $consent->save();

        AuditRecorder::record($consent, 'consent_revoked', $oldValues, ['revoked_at' => $consent->revoked_at]);

        return back()->with('success', 'Consenso revocato.');
    }
}
