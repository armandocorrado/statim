import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PatientPicker from '@/Components/PatientPicker';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = [
    { value: 'scheduled', label: 'Programmato' },
    { value: 'confirmed', label: 'Confermato' },
    { value: 'completed', label: 'Completato' },
    { value: 'cancelled', label: 'Annullato' },
    { value: 'no_show', label: 'Non presentato' },
];

const ROLE_LABELS = {
    odontoiatra: 'Odontoiatra',
    igienista: 'Igienista',
    aso: 'Assistente alla poltrona',
    admin: 'Titolare',
    segreteria: 'Segreteria',
};

function toDateTimeLocal(value) {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
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

    const asoOptions = operators.filter((op) => op.role === 'aso');

    const { data, setData, post, patch, processing, errors } = useForm({
        patient_id: appointment?.patient?.id ?? null,
        operator_id: appointment?.operator?.id ?? (canManageAll ? '' : currentUserId),
        assistant_id: appointment?.assistant?.id ?? '',
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
                        id="patient_search"
                        value={data.patient_id}
                        initialLabel={patientLabel}
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
                                        {op.name} — {ROLE_LABELS[op.role] ?? op.role}
                                    </option>
                                ))}
                        </select>
                        <InputError message={errors.operator_id} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="assistant_id" value="Assistente alla poltrona" />
                        <select
                            id="assistant_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.assistant_id}
                            onChange={(e) => setData('assistant_id', e.target.value)}
                        >
                            <option value="">—</option>
                            {asoOptions.map((op) => (
                                <option key={op.id} value={op.id}>
                                    {op.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.assistant_id} className="mt-2" />
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
                    {errors.assistant_overlap && (
                        <p className="text-sm text-red-600">{errors.assistant_overlap}</p>
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
