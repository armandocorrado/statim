import AppointmentModal from '@/Components/AppointmentModal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const STATUS_LABELS = {
    scheduled: 'Programmato',
    confirmed: 'Confermato',
    completed: 'Completato',
    cancelled: 'Annullato',
    no_show: 'Non presentato',
};

const STATUS_STYLES = {
    scheduled: 'bg-gray-100 text-gray-700',
    confirmed: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-500 line-through',
    no_show: 'bg-amber-100 text-amber-700',
};

// Titolo/colore per profilo — le card operatore si distinguono a colpo
// d'occhio in una vista multi-operatore. Ruoli fissi (RBAC), stesso
// principio delle altre mappe etichetta di questa pagina.
const ROLE_PROFILES = {
    odontoiatra: { label: 'Odontoiatra', border: 'border-t-blue-600', badge: 'bg-blue-100 text-blue-700' },
    igienista: { label: 'Igienista', border: 'border-t-teal-600', badge: 'bg-teal-100 text-teal-700' },
    aso: { label: 'Assistente alla poltrona', border: 'border-t-amber-600', badge: 'bg-amber-100 text-amber-700' },
    admin: { label: 'Titolare', border: 'border-t-slate-600', badge: 'bg-slate-100 text-slate-700' },
    segreteria: { label: 'Segreteria', border: 'border-t-purple-600', badge: 'bg-purple-100 text-purple-700' },
};

function roleProfile(role) {
    return ROLE_PROFILES[role] ?? { label: role, border: 'border-t-gray-400', badge: 'bg-gray-100 text-gray-700' };
}

function formatTime(value) {
    return new Date(value).toLocaleTimeString('it-IT', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function AppointmentCard({ appointment, onEdit }) {
    return (
        <button
            type="button"
            onClick={() => onEdit(appointment)}
            className="mb-2 block w-full rounded-md border-l-4 bg-white p-2 text-left text-sm shadow-sm hover:shadow"
            style={{ borderLeftColor: appointment.type?.color ?? '#94a3b8' }}
        >
            <div className="flex items-center justify-between">
                <span className="font-medium text-slate-900">
                    {formatTime(appointment.start_at)}–
                    {formatTime(appointment.end_at)}
                </span>
                <span
                    className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLES[appointment.status]}`}
                >
                    {STATUS_LABELS[appointment.status]}
                </span>
            </div>
            <div className="mt-1 text-slate-700">
                {appointment.patient
                    ? `${appointment.patient.first_name} ${appointment.patient.last_name}`
                    : 'Bloccato / non disponibile'}
            </div>
            {appointment.type && (
                <div className="mt-0.5 text-xs text-gray-500">
                    {appointment.type.name}
                </div>
            )}
        </button>
    );
}

export default function Index({
    view,
    date,
    operatorId,
    operators,
    appointments,
    appointmentTypes,
    canManageAll,
    canManageOwn,
}) {
    const currentUserId = usePage().props.auth.user.id;
    const canManage = canManageAll || canManageOwn;

    const [modalState, setModalState] = useState(null); // { appointment } | { create: true } | null

    const navigate = (params) => {
        router.get(
            route('agenda.index'),
            { view, date, operator_id: operatorId, ...params },
            { preserveState: true, preserveScroll: true },
        );
    };

    const shiftDate = (days) => {
        const d = new Date(date);
        d.setDate(d.getDate() + days);
        navigate({ date: d.toISOString().slice(0, 10) });
    };

    const dayColumns = useMemo(() => {
        const targetOperators = operatorId
            ? operators.filter((o) => o.id === operatorId)
            : operators;

        return targetOperators.map((op) => ({
            operator: op,
            appointments: appointments.filter((a) => a.operator.id === op.id),
        }));
    }, [operators, appointments, operatorId]);

    const weekOperatorId = operatorId || operators[0]?.id;
    const weekOperator = operators.find((o) => o.id === weekOperatorId);
    const weekDays = useMemo(() => {
        if (view !== 'week') return [];
        const start = new Date(date);
        const day = start.getDay();
        const mondayOffset = day === 0 ? -6 : 1 - day;
        start.setDate(start.getDate() + mondayOffset);

        return Array.from({ length: 7 }, (_, i) => {
            const d = new Date(start);
            d.setDate(d.getDate() + i);
            const iso = d.toISOString().slice(0, 10);
            return {
                date: d,
                iso,
                appointments: appointments.filter(
                    (a) =>
                        a.operator.id === weekOperatorId &&
                        a.start_at.slice(0, 10) === iso,
                ),
            };
        });
    }, [view, date, appointments, weekOperatorId]);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Agenda
                </h2>
            }
        >
            <Head title="Agenda" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <SecondaryButton onClick={() => shiftDate(view === 'week' ? -7 : -1)}>
                                ‹
                            </SecondaryButton>
                            <SecondaryButton
                                onClick={() =>
                                    navigate({
                                        date: new Date().toISOString().slice(0, 10),
                                    })
                                }
                            >
                                Oggi
                            </SecondaryButton>
                            <SecondaryButton onClick={() => shiftDate(view === 'week' ? 7 : 1)}>
                                ›
                            </SecondaryButton>
                            <span className="ml-2 text-sm font-medium text-slate-700">
                                {new Date(date).toLocaleDateString('it-IT', {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </span>
                        </div>

                        <div className="flex items-center gap-2">
                            <select
                                className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={operatorId ?? ''}
                                onChange={(e) =>
                                    navigate({ operator_id: e.target.value || null })
                                }
                            >
                                <option value="">Tutti gli operatori</option>
                                {operators.map((op) => (
                                    <option key={op.id} value={op.id}>
                                        {op.name} — {roleProfile(op.role).label}
                                    </option>
                                ))}
                            </select>

                            <SecondaryButton
                                onClick={() => navigate({ view: 'day' })}
                                className={view === 'day' ? 'bg-gray-200' : ''}
                            >
                                Giorno
                            </SecondaryButton>
                            <SecondaryButton
                                onClick={() => navigate({ view: 'week' })}
                                className={view === 'week' ? 'bg-gray-200' : ''}
                            >
                                Settimana
                            </SecondaryButton>

                            {canManage && (
                                <PrimaryButton
                                    onClick={() => setModalState({ create: true })}
                                >
                                    Nuovo appuntamento
                                </PrimaryButton>
                            )}
                        </div>
                    </div>

                    {view === 'day' && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {dayColumns.map(({ operator, appointments: ops }) => {
                                const profile = roleProfile(operator.role);

                                return (
                                <div
                                    key={operator.id}
                                    className={`rounded-lg border-t-4 bg-gray-50 p-3 ${profile.border}`}
                                >
                                    <h3 className="text-sm font-semibold text-slate-800">
                                        {operator.name}
                                    </h3>
                                    <span
                                        className={`mb-2 mt-0.5 inline-block rounded-full px-2 py-0.5 text-xs ${profile.badge}`}
                                    >
                                        {profile.label}
                                    </span>
                                    {ops.length === 0 && (
                                        <p className="text-xs text-gray-400">
                                            Nessun appuntamento
                                        </p>
                                    )}
                                    {ops.map((appointment) => (
                                        <AppointmentCard
                                            key={appointment.id}
                                            appointment={appointment}
                                            onEdit={(a) => setModalState({ appointment: a })}
                                        />
                                    ))}
                                </div>
                                );
                            })}
                        </div>
                    )}

                    {view === 'week' && (
                        <>
                            {weekOperator && (
                                <div className="mb-3 flex items-center gap-2">
                                    <span className="text-sm font-medium text-slate-800">
                                        {weekOperator.name}
                                    </span>
                                    <span
                                        className={`inline-block rounded-full px-2 py-0.5 text-xs ${roleProfile(weekOperator.role).badge}`}
                                    >
                                        {roleProfile(weekOperator.role).label}
                                    </span>
                                </div>
                            )}
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-7">
                                {weekDays.map((day) => (
                                    <div
                                        key={day.iso}
                                        className={`rounded-lg border-t-4 bg-gray-50 p-3 ${roleProfile(weekOperator?.role).border}`}
                                    >
                                        <h3 className="mb-2 text-sm font-semibold text-slate-800">
                                            {day.date.toLocaleDateString('it-IT', {
                                                weekday: 'short',
                                                day: 'numeric',
                                            })}
                                        </h3>
                                        {day.appointments.length === 0 && (
                                            <p className="text-xs text-gray-400">—</p>
                                        )}
                                        {day.appointments.map((appointment) => (
                                            <AppointmentCard
                                                key={appointment.id}
                                                appointment={appointment}
                                                onEdit={(a) => setModalState({ appointment: a })}
                                            />
                                        ))}
                                    </div>
                                ))}
                            </div>
                        </>
                    )}

                </div>
            </div>

            {modalState && (
                <AppointmentModal
                    appointment={modalState.appointment}
                    operators={operators}
                    appointmentTypes={appointmentTypes}
                    canManageAll={canManageAll}
                    currentUserId={currentUserId}
                    onClose={() => setModalState(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
