<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Http\Requests\StoreDentalAlertRequest;
use App\Modules\Dental\Models\DentalAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DentalAlertController extends Controller
{
    public function store(StoreDentalAlertRequest $request, Patient $patient): RedirectResponse
    {
        $alert = new DentalAlert($request->validated());
        $alert->patient_id = $patient->id;
        $alert->tenant_id = $patient->tenant_id;
        $alert->created_by = $request->user()->id;
        $alert->save();

        return back()->with('success', 'Alert registrato.');
    }

    public function resolve(Request $request, Patient $patient, DentalAlert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);

        abort_if($alert->patient_id !== $patient->id, 404);

        $alert->is_active = false;
        $alert->save();

        return back()->with('success', 'Alert risolto.');
    }
}
