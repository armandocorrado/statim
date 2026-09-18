<?php

namespace App\Core\Agenda\Http\Controllers;

use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Models\AppointmentType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $user = $request->user();
        $view = $request->string('view', 'day')->value();
        $view = in_array($view, ['day', 'week'], true) ? $view : 'day';

        $date = CarbonImmutable::parse($request->string('date')->toString() ?: now());
        $rangeStart = $view === 'week' ? $date->startOfWeek() : $date->startOfDay();
        $rangeEnd = $view === 'week' ? $date->startOfWeek()->addDays(7) : $date->addDay()->startOfDay();

        $operatorId = $request->string('operator_id')->toString() ?: null;

        $operators = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (User $candidate) => $candidate->can('agenda.view.own')
                || $candidate->can('agenda.manage.all')
                || $candidate->can('agenda.view.all'))
            ->map(fn (User $candidate) => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'role' => $candidate->getRoleNames()->first(),
            ])
            ->values();

        $appointments = Appointment::query()
            ->with(['patient:id,first_name,last_name', 'operator:id,name', 'assistant:id,name', 'type:id,name,color'])
            ->where('start_at', '<', $rangeEnd)
            ->where('end_at', '>', $rangeStart)
            ->when(
                $operatorId,
                fn ($query) => $query->where(fn ($q) => $q->where('operator_id', $operatorId)->orWhere('assistant_id', $operatorId)),
            )
            ->when(
                ! $user->can('agenda.view.all'),
                fn ($query) => $query->where('operator_id', $user->id),
            )
            ->orderBy('start_at')
            ->get();

        // `operators` resta l'elenco COMPLETO dello studio — serve al modale
        // appuntamento per assegnare operatore/assistente (un odontoiatra
        // deve poter scegliere un ASO come assistente anche se non vede
        // l'agenda altrui). `visibleOperators` è invece ciò che alimenta la
        // griglia/il filtro dell'agenda: chi non ha `agenda.view.all` (oggi
        // odontoiatra/igienista) vede solo se stesso, coerente col filtro
        // già applicato sopra a `$appointments` — niente colonne vuote per
        // i colleghi.
        $canViewAll = $user->can('agenda.view.all');
        $visibleOperators = $canViewAll
            ? $operators
            : $operators->where('id', $user->id)->values();

        return Inertia::render('Agenda/Index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'operatorId' => $operatorId,
            'operators' => $operators,
            'visibleOperators' => $visibleOperators,
            'canViewAll' => $canViewAll,
            'appointments' => $appointments,
            'appointmentTypes' => AppointmentType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'canManageAll' => $user->can('agenda.manage.all'),
            'canManageOwn' => $user->can('agenda.manage.own'),
        ]);
    }
}
