import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const QUOTE_STATUS_LABELS = {
    draft: 'Bozza',
    issued: 'Emesso',
    accepted: 'Accettato',
    rejected: 'Rifiutato',
    in_progress: 'In corso',
    completed: 'Completato',
};

function formatDate(value) {
    if (!value) return null;
    return new Date(value).toLocaleDateString('it-IT');
}

function ToothPicker({ selected, onToggle, permanentTeeth, deciduousTeeth, errors }) {
    return (
        <div className="mt-2">
            <InputLabel value="Denti collegati (facoltativo)" />
            <div className="mt-1 flex flex-wrap gap-1">
                {permanentTeeth.map((tooth) => (
                    <button
                        key={tooth}
                        type="button"
                        onClick={() => onToggle(tooth)}
                        className={
                            'rounded border px-1.5 py-0.5 text-xs ' +
                            (selected.includes(tooth)
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-gray-300 bg-white text-slate-600')
                        }
                    >
                        {tooth}
                    </button>
                ))}
            </div>
            <details className="mt-1">
                <summary className="cursor-pointer text-xs text-gray-500">Denti decidui</summary>
                <div className="mt-1 flex flex-wrap gap-1">
                    {deciduousTeeth.map((tooth) => (
                        <button
                            key={tooth}
                            type="button"
                            onClick={() => onToggle(tooth)}
                            className={
                                'rounded border px-1.5 py-0.5 text-xs ' +
                                (selected.includes(tooth)
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-gray-300 bg-white text-slate-600')
                            }
                        >
                            {tooth}
                        </button>
                    ))}
                </div>
            </details>
            <InputError message={errors?.['teeth.0']} />
        </div>
    );
}

function ItemRow({ patientId, planId, item, serviceCatalogItems, permanentTeeth, deciduousTeeth }) {
    const { data, setData, put, delete: destroy, processing, errors } = useForm({
        service_catalog_item_id: item.service_catalog_item_id,
        quantity: item.quantity,
        notes: item.notes ?? '',
        session_group: item.session_group ?? '',
        teeth: item.teeth.map((t) => t.tooth_number),
    });

    const toggleTooth = (tooth) => {
        setData('teeth', data.teeth.includes(tooth) ? data.teeth.filter((t) => t !== tooth) : [...data.teeth, tooth]);
    };

    const save = (e) => {
        e.preventDefault();
        put(route('dental.treatment-plans.items.update', [patientId, planId, item.id]), { preserveScroll: true });
    };

    const remove = () => {
        if (confirm('Eliminare questa voce dal piano di cura?')) {
            destroy(route('dental.treatment-plans.items.destroy', [patientId, planId, item.id]), { preserveScroll: true });
        }
    };

    return (
        <form onSubmit={save} className="rounded-md border border-gray-200 p-3">
            <div className="flex flex-wrap items-start gap-3">
                <div className="min-w-[12rem] flex-1">
                    <InputLabel value="Prestazione" />
                    <select
                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.service_catalog_item_id ?? ''}
                        onChange={(e) => setData('service_catalog_item_id', e.target.value)}
                    >
                        <option value="">— Seleziona —</option>
                        {serviceCatalogItems.map((catalogItem) => (
                            <option key={catalogItem.id} value={catalogItem.id}>
                                {catalogItem.name} — {catalogItem.base_price} €
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.service_catalog_item_id} />
                </div>
                <div className="w-20">
                    <InputLabel value="Qtà" />
                    <input
                        type="number"
                        step="0.01"
                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.quantity}
                        onChange={(e) => setData('quantity', e.target.value)}
                    />
                </div>
                <div className="w-40">
                    <InputLabel value="Seduta (facoltativo)" />
                    <input
                        type="text"
                        placeholder="es. Seduta 1"
                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.session_group}
                        onChange={(e) => setData('session_group', e.target.value)}
                    />
                </div>
                <button
                    type="button"
                    onClick={remove}
                    disabled={processing}
                    className="ml-auto text-red-600 hover:underline"
                >
                    Elimina
                </button>
            </div>

            <ToothPicker
                selected={data.teeth}
                onToggle={toggleTooth}
                permanentTeeth={permanentTeeth}
                deciduousTeeth={deciduousTeeth}
                errors={errors}
            />

            <div className="mt-2">
                <InputLabel value="Note cliniche" />
                <textarea
                    rows={2}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </div>

            <SecondaryButton type="submit" className="mt-2" disabled={processing}>
                Salva modifiche
            </SecondaryButton>
        </form>
    );
}

function NewItemForm({ patientId, planId, serviceCatalogItems, permanentTeeth, deciduousTeeth }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        service_catalog_item_id: '',
        quantity: 1,
        notes: '',
        session_group: '',
        teeth: [],
    });

    const toggleTooth = (tooth) => {
        setData('teeth', data.teeth.includes(tooth) ? data.teeth.filter((t) => t !== tooth) : [...data.teeth, tooth]);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('dental.treatment-plans.items.store', [patientId, planId]), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-md border border-dashed border-gray-300 bg-gray-50 p-3">
            <h4 className="mb-2 text-sm font-semibold text-slate-700">Nuova voce</h4>
            <div className="flex flex-wrap items-start gap-3">
                <div className="min-w-[12rem] flex-1">
                    <InputLabel value="Prestazione" />
                    <select
                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.service_catalog_item_id}
                        onChange={(e) => setData('service_catalog_item_id', e.target.value)}
                    >
                        <option value="">— Seleziona —</option>
                        {serviceCatalogItems.map((catalogItem) => (
                            <option key={catalogItem.id} value={catalogItem.id}>
                                {catalogItem.name} — {catalogItem.base_price} €
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.service_catalog_item_id} />
                </div>
                <div className="w-20">
                    <InputLabel value="Qtà" />
                    <input
                        type="number"
                        step="0.01"
                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.quantity}
                        onChange={(e) => setData('quantity', e.target.value)}
                    />
                </div>
                <div className="w-40">
                    <InputLabel value="Seduta (facoltativo)" />
                    <input
                        type="text"
                        placeholder="es. Seduta 1"
                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.session_group}
                        onChange={(e) => setData('session_group', e.target.value)}
                    />
                </div>
            </div>

            <ToothPicker
                selected={data.teeth}
                onToggle={toggleTooth}
                permanentTeeth={permanentTeeth}
                deciduousTeeth={deciduousTeeth}
                errors={errors}
            />

            <div className="mt-2">
                <InputLabel value="Note cliniche" />
                <textarea
                    rows={2}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </div>

            <PrimaryButton type="submit" className="mt-2" disabled={processing}>
                Aggiungi voce
            </PrimaryButton>
        </form>
    );
}

export default function Show({
    patient,
    plan,
    quotes,
    serviceCatalogItems,
    permanentTeeth,
    deciduousTeeth,
    canManage,
    canManageHygieneOnly,
    canGenerateQuote,
}) {
    const visibleCatalog = canManageHygieneOnly
        ? serviceCatalogItems.filter((item) => item.category === 'hygiene')
        : serviceCatalogItems;

    const generateQuote = () => {
        if (confirm('Generare un nuovo preventivo dalle voci attuali del piano?')) {
            router.post(route('dental.treatment-plans.generate-quote', [patient.id, plan.id]));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {plan.title || 'Piano di cura'} — {patient.last_name} {patient.first_name}
                </h2>
            }
        >
            <Head title={`Piano di cura — ${patient.last_name} ${patient.first_name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    <Link
                        href={route('dental.treatment-plans.index', patient.id)}
                        className="text-sm text-indigo-600 hover:underline"
                    >
                        ← Torna ai piani di cura
                    </Link>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-4 text-lg font-medium text-slate-900">Voci del piano</h3>
                        {canManage ? (
                            <div className="space-y-4">
                                {plan.items.map((item) => (
                                    <ItemRow
                                        key={item.id}
                                        patientId={patient.id}
                                        planId={plan.id}
                                        item={item}
                                        serviceCatalogItems={visibleCatalog}
                                        permanentTeeth={permanentTeeth}
                                        deciduousTeeth={deciduousTeeth}
                                    />
                                ))}
                                <NewItemForm
                                    patientId={patient.id}
                                    planId={plan.id}
                                    serviceCatalogItems={visibleCatalog}
                                    permanentTeeth={permanentTeeth}
                                    deciduousTeeth={deciduousTeeth}
                                />
                            </div>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {plan.items.map((item) => (
                                    <li key={item.id} className="border-b border-gray-100 pb-2">
                                        <span className="font-medium text-slate-800">
                                            {item.service_catalog_item.name}
                                        </span>{' '}
                                        <span className="text-gray-500">
                                            × {item.quantity}
                                            {item.teeth.length > 0 &&
                                                ` — dente ${item.teeth.map((t) => t.tooth_number).join(', ')}`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-medium text-slate-900">Preventivi generati</h3>
                            {canGenerateQuote && (
                                <SecondaryButton type="button" onClick={generateQuote}>
                                    Genera preventivo
                                </SecondaryButton>
                            )}
                        </div>
                        {quotes.length === 0 && <p className="text-sm text-gray-400">Nessun preventivo generato da questo piano.</p>}
                        <ul className="space-y-2 text-sm">
                            {quotes.map((quote) => (
                                <li key={quote.id} className="flex items-center justify-between border-b border-gray-100 pb-2">
                                    <Link href={route('quotes.show', quote.id)} className="text-indigo-600 hover:underline">
                                        Preventivo del {formatDate(quote.created_at)}
                                    </Link>
                                    <span className="text-xs text-gray-500">
                                        {QUOTE_STATUS_LABELS[quote.status]}
                                        {quote.total_amount && ` — ${quote.total_amount} €`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
