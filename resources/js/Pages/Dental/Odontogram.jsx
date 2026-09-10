import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function formatDate(value) {
    if (!value) return null;
    return new Date(value).toLocaleDateString('it-IT');
}

// Layout visivo standard di un cartellino odontoiatrico: arcata superiore
// e inferiore, quadrante del paziente a destra dello schermo a sinistra —
// non l'ordine numerico grezzo (11-18, 21-28, ...) ma quello con cui un
// clinico legge un odontogramma cartaceo.
const PERMANENT_LAYOUT = {
    top: ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'],
    bottom: ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'],
};
const DECIDUOUS_LAYOUT = {
    top: ['55', '54', '53', '52', '51', '61', '62', '63', '64', '65'],
    bottom: ['85', '84', '83', '82', '81', '71', '72', '73', '74', '75'],
};

const CONDITION_COLORS = {
    carious: 'bg-red-500 text-white border-red-600',
    filled: 'bg-blue-500 text-white border-blue-600',
    missing: 'bg-gray-300 text-gray-500 border-gray-400 line-through',
    to_extract: 'bg-orange-500 text-white border-orange-600',
    implant: 'bg-purple-500 text-white border-purple-600',
    crown: 'bg-yellow-400 text-yellow-900 border-yellow-500',
    root_canal_treated: 'bg-pink-500 text-white border-pink-600',
    fractured: 'bg-amber-700 text-white border-amber-800',
    bridge: 'bg-teal-500 text-white border-teal-600',
    sealant: 'bg-green-500 text-white border-green-600',
};
const HEALTHY_COLOR = 'bg-white text-slate-600 border-gray-300';

function Tooth({ number, condition, isSelected, onClick }) {
    const colorClasses = condition ? CONDITION_COLORS[condition] ?? HEALTHY_COLOR : HEALTHY_COLOR;

    return (
        <button
            type="button"
            onClick={() => onClick(number)}
            className={
                'flex h-10 w-10 items-center justify-center rounded-md border-2 text-xs font-semibold transition ' +
                colorClasses +
                (isSelected ? ' ring-2 ring-offset-1 ring-indigo-600' : '')
            }
            title={number}
        >
            {number}
        </button>
    );
}

function Arch({ layout, currentStates, selectedTooth, onSelectTooth }) {
    return (
        <div className="space-y-2">
            <div className="flex justify-center gap-1">
                {layout.top.map((tooth) => (
                    <Tooth
                        key={tooth}
                        number={tooth}
                        condition={currentStates[tooth]?.condition_type}
                        isSelected={selectedTooth === tooth}
                        onClick={onSelectTooth}
                    />
                ))}
            </div>
            <div className="mx-auto h-px w-full max-w-3xl bg-gray-200" />
            <div className="flex justify-center gap-1">
                {layout.bottom.map((tooth) => (
                    <Tooth
                        key={tooth}
                        number={tooth}
                        condition={currentStates[tooth]?.condition_type}
                        isSelected={selectedTooth === tooth}
                        onClick={onSelectTooth}
                    />
                ))}
            </div>
        </div>
    );
}

function Legend({ conditionOptions }) {
    return (
        <div className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600">
            <span className="flex items-center gap-1">
                <span className={`inline-block h-3 w-3 rounded border ${HEALTHY_COLOR}`} />
                Sano / non esaminato
            </span>
            {conditionOptions.map((option) => (
                <span key={option.value} className="flex items-center gap-1">
                    <span
                        className={`inline-block h-3 w-3 rounded border ${CONDITION_COLORS[option.value] ?? HEALTHY_COLOR}`}
                    />
                    {option.label}
                </span>
            ))}
        </div>
    );
}

function ToothPanel({ patientId, tooth, history, diaryEntries, conditionOptions, canManage, onDone }) {
    const toothHistory = history.filter((record) => record.tooth_number === tooth);
    const current = toothHistory[0] ?? null;

    const { data, setData, post, processing, errors, reset } = useForm({
        tooth_number: tooth,
        condition_type: conditionOptions[0]?.value ?? '',
        recorded_date: new Date().toISOString().slice(0, 10),
        diary_entry_id: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('dental.odontogram.store', patientId), {
            preserveScroll: true,
            onSuccess: () => {
                reset('notes', 'diary_entry_id');
                onDone?.();
            },
        });
    };

    return (
        <div className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-1 text-lg font-medium text-slate-900">Dente {tooth}</h3>
            <p className="mb-4 text-sm text-slate-500">
                Stato attuale:{' '}
                <span className="font-medium text-slate-800">
                    {current
                        ? conditionOptions.find((o) => o.value === current.condition_type)?.label ?? current.condition_type
                        : 'Sano / non esaminato'}
                </span>
            </p>

            {canManage && (
                <form onSubmit={submit} className="mb-6 space-y-3 rounded-md bg-gray-50 p-3">
                    <div className="flex flex-wrap gap-3">
                        <div>
                            <InputLabel htmlFor="condition_type" value="Nuovo stato" />
                            <select
                                id="condition_type"
                                className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.condition_type}
                                onChange={(e) => setData('condition_type', e.target.value)}
                            >
                                {conditionOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="recorded_date" value="Data" />
                            <input
                                id="recorded_date"
                                type="date"
                                className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.recorded_date}
                                onChange={(e) => setData('recorded_date', e.target.value)}
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="diary_entry_id" value="Nota di diario collegata" />
                            <select
                                id="diary_entry_id"
                                className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.diary_entry_id}
                                onChange={(e) => setData('diary_entry_id', e.target.value)}
                            >
                                <option value="">— Nessuna —</option>
                                {diaryEntries.map((entry) => (
                                    <option key={entry.id} value={entry.id}>
                                        {formatDate(entry.entry_date)} ({entry.section === 'hygiene' ? 'Igiene' : 'Generale'})
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel htmlFor="notes" value="Note" />
                        <textarea
                            id="notes"
                            rows={2}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        {errors.notes && <p className="mt-1 text-xs text-red-600">{errors.notes}</p>}
                    </div>
                    <SecondaryButton type="submit" disabled={processing}>
                        Registra stato
                    </SecondaryButton>
                </form>
            )}

            <h4 className="mb-2 text-sm font-semibold text-slate-700">Storico</h4>
            {toothHistory.length === 0 && <p className="text-sm text-gray-400">Nessun evento registrato per questo dente.</p>}
            <ul className="space-y-2">
                {toothHistory.map((record) => (
                    <li key={record.id} className="border-b border-gray-100 pb-2 text-sm">
                        <div className="flex items-center gap-2 text-xs text-gray-500">
                            <span>{formatDate(record.recorded_date)}</span>
                            <span>—</span>
                            <span>{record.operator.name}</span>
                            {record.diary_entry && (
                                <span className="rounded-full bg-gray-100 px-2 py-0.5">
                                    diario {formatDate(record.diary_entry.entry_date)}
                                </span>
                            )}
                        </div>
                        <p className="mt-0.5 font-medium text-slate-800">
                            {conditionOptions.find((o) => o.value === record.condition_type)?.label ?? record.condition_type}
                        </p>
                        {record.notes && <p className="mt-0.5 whitespace-pre-wrap text-slate-600">{record.notes}</p>}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function Odontogram({
    patient,
    permanentTeeth,
    deciduousTeeth,
    currentStates,
    history,
    conditionOptions,
    diaryEntries,
    canManage,
}) {
    const [dentition, setDentition] = useState('permanent');
    const [selectedTooth, setSelectedTooth] = useState(null);

    const layout = dentition === 'permanent' ? PERMANENT_LAYOUT : DECIDUOUS_LAYOUT;
    const validTeeth = useMemo(
        () => new Set(dentition === 'permanent' ? permanentTeeth : deciduousTeeth),
        [dentition, permanentTeeth, deciduousTeeth],
    );

    const selectTooth = (tooth) => setSelectedTooth(tooth === selectedTooth ? null : tooth);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Odontogramma — {patient.last_name} {patient.first_name}
                </h2>
            }
        >
            <Head title={`Odontogramma — ${patient.last_name} ${patient.first_name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between">
                        <Link href={route('dental.show', patient.id)} className="text-sm text-indigo-600 hover:underline">
                            ← Torna alla cartella clinica
                        </Link>
                        <div className="flex overflow-hidden rounded-md border border-gray-300">
                            <button
                                type="button"
                                onClick={() => setDentition('permanent')}
                                className={`px-3 py-1.5 text-sm ${dentition === 'permanent' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'}`}
                            >
                                Permanenti
                            </button>
                            <button
                                type="button"
                                onClick={() => setDentition('deciduous')}
                                className={`px-3 py-1.5 text-sm ${dentition === 'deciduous' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'}`}
                            >
                                Decidui
                            </button>
                        </div>
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <Arch
                            layout={layout}
                            currentStates={currentStates}
                            selectedTooth={selectedTooth}
                            onSelectTooth={selectTooth}
                        />
                        <div className="mt-6 border-t border-gray-100 pt-4">
                            <Legend conditionOptions={conditionOptions} />
                        </div>
                    </div>

                    {selectedTooth && validTeeth.has(selectedTooth) && (
                        <ToothPanel
                            patientId={patient.id}
                            tooth={selectedTooth}
                            history={history}
                            diaryEntries={diaryEntries}
                            conditionOptions={conditionOptions}
                            canManage={canManage}
                            onDone={() => {}}
                        />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
