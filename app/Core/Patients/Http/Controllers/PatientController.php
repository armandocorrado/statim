<?php

namespace App\Core\Patients\Http\Controllers;

use App\Core\Consents\Enums\ConsentCollectionMethod;
use App\Core\Consents\Enums\ConsentPurpose;
use App\Core\Consents\Enums\PolicyVersion;
use App\Core\Patients\Http\Requests\StorePatientRequest;
use App\Core\Patients\Http\Requests\UpdatePatientRequest;
use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->when($request->string('search')->toString(), function ($query, $search) {
                $needle = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($needle) {
                    $query->whereRaw('LOWER(first_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$needle]);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Patients/Index', [
            'patients' => $patients,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Patient::class);

        return Inertia::render('Patients/Create');
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = new Patient($request->validated());
        $patient->created_by = $request->user()->id;
        $patient->updated_by = $request->user()->id;
        $patient->save();

        return to_route('patients.show', $patient)
            ->with('success', 'Paziente creato correttamente.');
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);

        return Inertia::render('Patients/Show', [
            'patient' => $patient->load(['guardian', 'consents' => fn ($query) => $query->latest('granted_at')]),
            'consentOptions' => [
                'purposes' => self::enumOptions(ConsentPurpose::cases()),
                'collectionMethods' => self::enumOptions(ConsentCollectionMethod::cases()),
                'policyVersions' => self::enumOptions(PolicyVersion::cases()),
            ],
        ]);
    }

    /**
     * @param  list<ConsentPurpose|ConsentCollectionMethod|PolicyVersion>  $cases
     * @return list<array{value: string, label: string}>
     */
    private static function enumOptions(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    public function edit(Patient $patient): Response
    {
        $this->authorize('update', $patient);

        return Inertia::render('Patients/Edit', [
            'patient' => $patient,
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->fill($request->validated());
        $patient->updated_by = $request->user()->id;
        $patient->save();

        return to_route('patients.show', $patient)
            ->with('success', 'Paziente aggiornato correttamente.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);

        $patient->delete();

        return to_route('patients.index')
            ->with('success', 'Paziente disattivato.');
    }
}
