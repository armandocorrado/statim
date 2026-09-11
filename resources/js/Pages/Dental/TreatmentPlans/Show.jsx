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

const EMPTY_ITEM = { service_catalog_item_id: '', quantity: 1, notes: '', session_group: '', teeth: [] };

function ItemsEditor({ patientId, plan, serviceCatalogItems, permanentTeeth, deciduousTeeth }) {
    const { data, setData, put, processing, errors } = useForm({
        items: plan.items.map((item) => ({
            service_catalog_item_id: item.service_catalog_item_id,
            quantity: item.quantity,
            notes: item.notes ?? '',
            session_group: item.session_group ?? '',
            teeth: item.teeth.map((t) => t.tooth_number),
        })),
    });

    const updateItem = (index, field, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const toggleTooth = (index, tooth) => {
        const current = data.items[index].teeth;
        updateItem(
            index,
            'teeth',
            current.includes(tooth) ? current.filter((t) => t !== tooth) : [...current, tooth],
        );
    };

    const addItem = () => setData('items', [...data.items, { ...EMPTY_ITEM }]);
    const removeItem = (index) => setData('items', data.items.filter((_, i) => i !== index));

    const submit = (e) => {
        e.preventDefault();
        put(route('dental.treatment-plans.update', [patientId, plan.id]));
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            {data.items.map((item, index) => (
                <div key={index} className="rounded-md border border-gray-200 p-3">
                    <div className="flex flex-wrap items-start gap-3">
                        <div className="min-w-[12rem] flex-1">
                            <InputLabel value="Prestazione" />
                            <select
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={item.service_catalog_item_id ?? ''}
                                onChange={(e) => updateItem(index, 'service_catalog_item_id', e.target.value)}
                            >
                                <option value="">— Seleziona —</option>
                                {serviceCatalogItems.map((catalogItem) => (
                                    <option key={catalogItem.id} value={catalogItem.id}>
                                        {catalogItem.name} — {catalogItem.base_price} €
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors[`items.${index}.service_catalog_item_id`]} />
                        </div>
                        <div className="w-20">
                            <InputLabel value="Qtà" />
                            <input
                                type="number"
                                step="0.01"
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={item.quantity}
                                onChange={(e) => updateItem(index, 'quantity', e.target.value)}
                            />
                        </div>
                        <div className="w-40">
                            <InputLabel value="Seduta (facoltativo)" />
                            <input
                                type="text"
                                placeholder="es. Seduta 1"
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={item.session_group}
                                onChange={(e) => updateItem(index, 'session_group', e.target.value)}
                            />
                        </div>
                        <button
                            type="button"
                            onClick={() => removeItem(index)}
                            className="ml-auto text-red-600 hover:underline"
                        >
                            ✕
                        </button>
                    </div>

                    <div className="mt-2">
                        <InputLabel value="Denti collegati (facoltativo)" />
                        <div className="mt-1 flex flex-wrap gap-1">
                            {permanentTeeth.map((tooth) => (
                                <button
                                    key={tooth}
                                    type="button"
                                    onClick={() => toggleTooth(index, tooth)}
                                    className={
                                        'rounded border px-1.5 py-0.5 text-xs ' +
                                        (item.teeth.includes(tooth)
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
                                        onClick={() => toggleTooth(index, tooth)}
                                        className={
                                            'rounded border px-1.5 py-0.5 text-xs ' +
                                            (item.teeth.includes(tooth)
                                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                                : 'border-gray-300 bg-white text-slate-600')
                                        }
                                    >
                                        {tooth}
                                    </button>
                                ))}
                            </div>
                        </details>
                        <InputError message={errors[`items.${index}.teeth.0`]} />
                    </div>

                    <div className="mt-2">
                        <InputLabel value="Note cliniche" />
                        <textarea
                            rows={2}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={item.notes}
                            onChange={(e) => updateItem(index, 'notes', e.target.value)}
                        />
                    </div>
                </div>
            ))}

            <div className="flex items-center gap-3">
                <SecondaryButton type="button" onClick={addItem}>
                    Aggiungi voce
                </SecondaryButton>
                <PrimaryButton type="submit" disabled={processing}>
                    Salva piano
                </PrimaryButton>
            </div>
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
                            <ItemsEditor
                                patientId={patient.id}
                                plan={plan}
                                serviceCatalogItems={visibleCatalog}
                                permanentTeeth={permanentTeeth}
                                deciduousTeeth={deciduousTeeth}
                            />
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
                                        Preventivo del {quote.created_at.slice(0, 10)}
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
