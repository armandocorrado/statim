<?php

namespace App\Core\Agenda\Http\Controllers;

use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ricerca-paziente minimale per il form di creazione appuntamento — a
 * differenza del picker tutore (deliberatamente rimandato in Patients),
 * qui è indispensabile: non si può fissare un appuntamento senza scegliere
 * chi lo riceve.
 */
class PatientSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $search = $request->string('q')->toString();

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $needle = '%'.mb_strtolower($search).'%';

        $patients = Patient::query()
            ->where('is_active', true)
            ->where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$needle]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name']);

        return response()->json($patients);
    }
}
