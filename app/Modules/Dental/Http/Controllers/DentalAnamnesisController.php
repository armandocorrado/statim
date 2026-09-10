<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Http\Requests\UpdateDentalAnamnesisRequest;
use App\Modules\Dental\Models\DentalAnamnesis;
use Illuminate\Http\RedirectResponse;

class DentalAnamnesisController extends Controller
{
    public function update(UpdateDentalAnamnesisRequest $request, Patient $patient): RedirectResponse
    {
        $anamnesis = DentalAnamnesis::firstOrNew(['patient_id' => $patient->id]);
        $anamnesis->fill($request->validated());
        $anamnesis->tenant_id = $patient->tenant_id;
        $anamnesis->updated_by = $request->user()->id;
        $anamnesis->save();

        return back()->with('success', 'Anamnesi aggiornata.');
    }
}
