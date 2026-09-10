<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Http\Requests\StoreDentalDiaryEntryRequest;
use App\Modules\Dental\Models\DentalDiaryEntry;
use Illuminate\Http\RedirectResponse;

class DentalDiaryEntryController extends Controller
{
    public function store(StoreDentalDiaryEntryRequest $request, Patient $patient): RedirectResponse
    {
        $entry = new DentalDiaryEntry($request->validated());
        $entry->patient_id = $patient->id;
        $entry->tenant_id = $patient->tenant_id;
        $entry->operator_id = $request->user()->id;
        $entry->save();

        return back()->with('success', 'Nota di diario aggiunta.');
    }
}
