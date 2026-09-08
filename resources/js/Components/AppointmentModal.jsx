import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const STATUSES = [
    { value: 'scheduled', label: 'Programmato' },
    { value: 'confirmed', label: 'Confermato' },
    { value: 'completed', label: 'Completato' },
    { value: 'cancelled', label: 'Annullato' },
    { value: 'no_show', label: 'Non presentato' },
];

function toDateTimeLocal(value) {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function PatientPicker({ value, label, onSelect, error }) {
    const [query, setQuery] = useState(label ?? '');
    const [results, setResults] = useState([]);
    const timeoutRef = useRef(null);

    useEffect(() => {
        setQuery(label ?? '');
    }, [label]);

    const search = (text) => {
        setQuery(text);
        onSelect(null, '');

        if (timeoutRef.current) clearTimeout(timeoutRef.current);
        if (text.trim().length < 2) {
            setResults([]);
            return;
        }

        timeoutRef.current = setTimeout(async () => {
            const response = await fetch(
                route('agenda.patients-search', { q: text }),
            );
            setResults(await response.json());
        }, 250);
    };

    return (
        <div className="relative">
            <InputLabel htmlFor="patient_search" value="Paziente" />
            <TextInput
                id="patient_search"
                className="mt-1 block w-full"
                value={query}
                placeholder="Cerca per nome o cognome…"
                onChange={(e) => search(e.target.value)}
                autoComplete="off"
            />
            <InputError message={error} className="mt-2" />
            {results.length > 0 && (
                <ul className="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                    {results.map((patient) => (
                        <li key={patient.id}>
                            <button
                                type="button"
                                className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                                onClick={() => {
                                    onSelect(
                                        patient.id,
                                        `${patient.first_name} ${patient.last_name}`,
                                    );
                                    setResults([]);
                                }}
                            >
                                {patient.first_name} {patient.last_name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function AppointmentModal({
    appointment,
    operators,
    appointmentTypes,
    canManageAll,
    currentUserId,
    onClose,
}) {
    const isEditing = Boolean(appointment);

    const { data, setData, post, patch, processing, errors } = useForm({
        patient_id: appointment?.patient?.id ?? null,
        operator_id: appointment?.operator?.id ?? (canManageAll ? '' : currentUserId),
        appointment_type_id: appointment?.type?.id ?? '',
        start_at: toDateTimeLocal(appointment?.start_at),
        end_at: toDateTimeLocal(appointment?.end_at),
        status: appointment?.status ?? 'scheduled',
        notes: appointment?.notes ?? '',
    });

    const [patientLabel, setPatientLabel] = useState(
        appointment?.patient
            ? `${appointment.patient.first_name} ${appointment.patient.last_name}`
            : '',
    );

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (isEditing) {
            patch(route('agenda.appointments.update', appointment.id), options);
        } else {
            post(route('agenda.appointments.store'), options);
        }
    };

    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-slate-900">
                        {isEditing ? 'Modifica appuntamento' : 'Nuovo appuntamento'}
                    </h3>
                    {appointment?.patient && (
                        <Link
                            href={route('patients.show', appointment.patient.id)}
                            className="text-sm text-indigo-600 hover:underline"
                        >
                            Apri scheda paziente →
                        </Link>
                    )}
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <PatientPicker
                        value={data.patient_id}
                        label={patientLabel}
                        error={errors.patient_id}
                        onSelect={(id, label) => {
                            setData('patient_id', id);
                            setPatientLabel(label);
                        }}
                    />

                    <div>
                        <InputLabel htmlFor="operator_id" value="Operatore" />
                        <select
                            id="operator_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                            value={data.operator_id}
                            disabled={!canManageAll}
                            onChange={(e) => setData('operator_id', e.target.value)}
                        >
                            {!canManageAll && (
                                <option value={currentUserId}>Io</option>
                            )}
                            {canManageAll && <option value="">—</option>}
                            {canManageAll &&
                                operators.map((op) => (
                                    <option key={op.id} value={op.id}>
                                        {op.name}
                                    </option>
                                ))}
                        </select>
                        <InputError message={errors.operator_id} className="mt-2" />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="start_at" value="Inizio" />
                            <TextInput
                                id="start_at"
                                type="datetime-local"
                                className="mt-1 block w-full"
                                value={data.start_at}
                                onChange={(e) =>
                                    setData('start_at', e.target.value)
                                }
                            />
                            <InputError message={errors.start_at} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="end_at" value="Fine" />
                            <TextInput
                                id="end_at"
                                type="datetime-local"
                                className="mt-1 block w-full"
                                value={data.end_at}
                                onChange={(e) => setData('end_at', e.target.value)}
                            />
                            <InputError message={errors.end_at} className="mt-2" />
                        </div>
                    </div>
                    {errors.overlap && (
                        <p className="text-sm text-red-600">{errors.overlap}</p>
                    )}

                    <div>
                        <InputLabel htmlFor="appointment_type_id" value="Tipo" />
                        <select
                            id="appointment_type_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.appointment_type_id}
                            onChange={(e) =>
                                setData('appointment_type_id', e.target.value)
                            }
                        >
                            <option value="">—</option>
                            {appointmentTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.appointment_type_id}
                            className="mt-2"
                        />
                    </div>

                    {isEditing && (
                        <div>
                            <InputLabel htmlFor="status" value="Stato" />
                            <select
                                id="status"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.status}
                                onChange={(e) =>
                                    setData('status', e.target.value)
                                }
                            >
                                {STATUSES.map(({ value, label }) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.status} className="mt-2" />
                        </div>
                    )}

                    <div>
                        <InputLabel htmlFor="notes" value="Note" />
                        <textarea
                            id="notes"
                            rows={3}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <InputError message={errors.notes} className="mt-2" />
                    </div>

                    <div className="flex items-center justify-end gap-3 pt-2">
                        <SecondaryButton type="button" onClick={onClose}>
                            Annulla
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={processing}>
                            Salva
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    );
}
