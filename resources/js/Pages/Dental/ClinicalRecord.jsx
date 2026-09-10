import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const ALERT_CATEGORY_LABELS = { allergy: 'Allergia', risk: 'Fattore di rischio' };
const SECTION_LABELS = { general: 'Generale', hygiene: 'Igiene' };

function AlertsBanner({ alerts, patientId, canManage }) {
    if (alerts.length === 0) return null;

    const resolve = (alert) => {
        if (confirm(`Segnare come risolto: "${alert.description}"?`)) {
            router.patch(
                route('dental.alerts.resolve', [patientId, alert.id]),
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="mb-6 rounded-lg border-2 border-red-400 bg-red-50 p-4">
            <h3 className="mb-2 text-sm font-bold uppercase tracking-wide text-red-800">
                ⚠ Alert attivi
            </h3>
            <ul className="space-y-1">
                {alerts.map((alert) => (
                    <li
                        key={alert.id}
                        className="flex items-center justify-between text-sm text-red-900"
                    >
                        <span>
                            <strong>
                                {ALERT_CATEGORY_LABELS[alert.category]}:
                            </strong>{' '}
                            {alert.description}
                        </span>
                        {canManage && (
                            <button
                                type="button"
                                onClick={() => resolve(alert)}
                                className="ml-4 text-xs text-red-700 hover:underline"
                            >
                                Segna come risolto
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function AnamnesisSection({ patientId, anamnesis, canManage }) {
    const { data, setData, put, processing } = useForm({
        pathologies: anamnesis?.pathologies ?? '',
        medications: anamnesis?.medications ?? '',
        risk_factors: anamnesis?.risk_factors ?? '',
        notes: anamnesis?.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('dental.anamnesis.update', patientId), { preserveScroll: true });
    };

    const fields = [
        ['pathologies', 'Patologie'],
        ['medications', 'Farmaci in uso'],
        ['risk_factors', 'Fattori di rischio'],
        ['notes', 'Note anamnestiche'],
    ];

    return (
        <div className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-4 text-lg font-medium text-slate-900">
                Anamnesi
            </h3>
            <form onSubmit={submit} className="space-y-4">
                {fields.map(([key, label]) => (
                    <div key={key}>
                        <InputLabel htmlFor={key} value={label} />
                        <textarea
                            id={key}
                            rows={2}
                            disabled={!canManage}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                            value={data[key]}
                            onChange={(e) => setData(key, e.target.value)}
                        />
                    </div>
                ))}
                {canManage && (
                    <PrimaryButton type="submit" disabled={processing}>
                        Salva anamnesi
                    </PrimaryButton>
                )}
            </form>
        </div>
    );
}

function AddAlertForm({ patientId }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        category: 'allergy',
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('dental.alerts.store', patientId), {
            preserveScroll: true,
            onSuccess: () => reset('description'),
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 flex flex-wrap items-end gap-3">
            <div>
                <InputLabel htmlFor="alert_category" value="Tipo" />
                <select
                    id="alert_category"
                    className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.category}
                    onChange={(e) => setData('category', e.target.value)}
                >
                    <option value="allergy">Allergia</option>
                    <option value="risk">Fattore di rischio</option>
                </select>
            </div>
            <div className="flex-1">
                <InputLabel htmlFor="alert_description" value="Descrizione" />
                <input
                    id="alert_description"
                    type="text"
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
                {errors.description && (
                    <p className="mt-1 text-xs text-red-600">
                        {errors.description}
                    </p>
                )}
            </div>
            <SecondaryButton type="submit" disabled={processing}>
                Aggiungi alert
            </SecondaryButton>
        </form>
    );
}

function DiarySection({ patientId, entries, canManage, hasFullAccess }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        entry_date: new Date().toISOString().slice(0, 10),
        section: hasFullAccess ? 'general' : 'hygiene',
        content: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('dental.diary.store', patientId), {
            preserveScroll: true,
            onSuccess: () => reset('content'),
        });
    };

    return (
        <div className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-4 text-lg font-medium text-slate-900">
                Diario clinico
            </h3>

            {canManage && (
                <form onSubmit={submit} className="mb-6 space-y-3 rounded-md bg-gray-50 p-3">
                    <div className="flex flex-wrap gap-3">
                        <div>
                            <InputLabel htmlFor="entry_date" value="Data" />
                            <input
                                id="entry_date"
                                type="date"
                                className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.entry_date}
                                onChange={(e) => setData('entry_date', e.target.value)}
                            />
                        </div>
                        {hasFullAccess && (
                            <div>
                                <InputLabel htmlFor="entry_section" value="Sezione" />
                                <select
                                    id="entry_section"
                                    className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.section}
                                    onChange={(e) => setData('section', e.target.value)}
                                >
                                    <option value="general">Generale</option>
                                    <option value="hygiene">Igiene</option>
                                </select>
                            </div>
                        )}
                    </div>
                    <textarea
                        rows={3}
                        placeholder="Testo clinico della seduta…"
                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.content}
                        onChange={(e) => setData('content', e.target.value)}
                    />
                    {errors.content && (
                        <p className="text-xs text-red-600">{errors.content}</p>
                    )}
                    <SecondaryButton type="submit" disabled={processing}>
                        Aggiungi nota
                    </SecondaryButton>
                </form>
            )}

            {entries.length === 0 && (
                <p className="text-sm text-gray-400">Nessuna nota di diario.</p>
            )}
            <ul className="space-y-3">
                {entries.map((entry) => (
                    <li key={entry.id} className="border-b border-gray-100 pb-3">
                        <div className="flex items-center gap-2 text-xs text-gray-500">
                            <span>{entry.entry_date}</span>
                            <span>—</span>
                            <span>{entry.operator.name}</span>
                            <span className="rounded-full bg-gray-100 px-2 py-0.5">
                                {SECTION_LABELS[entry.section]}
                            </span>
                        </div>
                        <p className="mt-1 whitespace-pre-wrap text-sm text-slate-800">
                            {entry.content}
                        </p>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function DocumentsSection({ patientId, documents, canManage, hasFullAccess }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        section: hasFullAccess ? 'general' : 'hygiene',
        document_type: 'referto',
        description: '',
        file: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('dental.documents.store', patientId), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => reset('description', 'file'),
        });
    };

    return (
        <div className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-4 text-lg font-medium text-slate-900">
                Documenti
            </h3>

            {canManage && (
                <form onSubmit={submit} className="mb-6 space-y-3 rounded-md bg-gray-50 p-3">
                    <div className="flex flex-wrap gap-3">
                        <div>
                            <InputLabel htmlFor="document_type" value="Tipo" />
                            <select
                                id="document_type"
                                className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.document_type}
                                onChange={(e) => setData('document_type', e.target.value)}
                            >
                                <option value="referto">Referto</option>
                                <option value="radiografia">Radiografia</option>
                                <option value="foto">Foto</option>
                                <option value="altro">Altro</option>
                            </select>
                        </div>
                        {hasFullAccess && (
                            <div>
                                <InputLabel htmlFor="document_section" value="Sezione" />
                                <select
                                    id="document_section"
                                    className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.section}
                                    onChange={(e) => setData('section', e.target.value)}
                                >
                                    <option value="general">Generale</option>
                                    <option value="hygiene">Igiene</option>
                                </select>
                            </div>
                        )}
                        <div className="flex-1">
                            <InputLabel htmlFor="document_description" value="Descrizione" />
                            <input
                                id="document_description"
                                type="text"
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                        </div>
                    </div>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(e) => setData('file', e.target.files[0])}
                        className="block text-sm"
                    />
                    {errors.file && (
                        <p className="text-xs text-red-600">{errors.file}</p>
                    )}
                    <SecondaryButton type="submit" disabled={processing}>
                        Carica documento
                    </SecondaryButton>
                </form>
            )}

            {documents.length === 0 && (
                <p className="text-sm text-gray-400">Nessun documento caricato.</p>
            )}
            <ul className="space-y-2">
                {documents.map((document) => (
                    <li
                        key={document.id}
                        className="flex items-center justify-between border-b border-gray-100 pb-2 text-sm"
                    >
                        <div>
                            <span className="font-medium text-slate-800">
                                {document.original_filename}
                            </span>{' '}
                            <span className="text-xs text-gray-500">
                                ({document.document_type},{' '}
                                {SECTION_LABELS[document.section]}) —{' '}
                                {document.description}
                            </span>
                        </div>
                        <a
                            href={route('dental.documents.download', [
                                patientId,
                                document.id,
                            ])}
                            className="text-indigo-600 hover:underline"
                        >
                            Scarica
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function ClinicalRecord({
    patient,
    anamnesis,
    alerts,
    diaryEntries,
    documents,
    hasFullAccess,
    canManage,
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Cartella clinica — {patient.last_name} {patient.first_name}
                </h2>
            }
        >
            <Head title={`Cartella clinica — ${patient.last_name} ${patient.first_name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    <Link
                        href={route('patients.show', patient.id)}
                        className="text-sm text-indigo-600 hover:underline"
                    >
                        ← Torna alla scheda paziente
                    </Link>

                    <AlertsBanner
                        alerts={alerts}
                        patientId={patient.id}
                        canManage={canManage}
                    />

                    {canManage && <AddAlertForm patientId={patient.id} />}

                    <AnamnesisSection
                        patientId={patient.id}
                        anamnesis={anamnesis}
                        canManage={canManage}
                    />

                    <DiarySection
                        patientId={patient.id}
                        entries={diaryEntries}
                        canManage={canManage}
                        hasFullAccess={hasFullAccess}
                    />

                    <DocumentsSection
                        patientId={patient.id}
                        documents={documents}
                        canManage={canManage}
                        hasFullAccess={hasFullAccess}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
