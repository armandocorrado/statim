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
            ->filter(fn (User $candidate) => $candidate->can('agenda.view.own') || $candidate->can('agenda.manage.all'))
            ->values();

        $appointments = Appointment::query()
            ->with(['patient:id,first_name,last_name', 'operator:id,name', 'type:id,name,color'])
            ->where('start_at', '<', $rangeEnd)
            ->where('end_at', '>', $rangeStart)
            ->when($operatorId, fn ($query) => $query->where('operator_id', $operatorId))
            ->when(
                ! $user->can('agenda.view.all'),
                fn ($query) => $query->where('operator_id', $user->id),
            )
            ->orderBy('start_at')
            ->get();

        return Inertia::render('Agenda/Index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'operatorId' => $operatorId,
            'operators' => $operators,
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
