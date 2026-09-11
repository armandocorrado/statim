import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const EMPTY_LINE = {
    service_catalog_item_id: null,
    description: '',
    quantity: 1,
    unit_price: '',
    discount_percent: '',
    vat_rate: '',
    vat_exemption_reason: '',
};

function computeTotals(lines) {
    let taxable = 0;

    for (const line of lines) {
        const discount = (parseFloat(line.discount_percent) || 0) / 100;
        const discountedUnitPrice = (parseFloat(line.unit_price) || 0) * (1 - discount);
        taxable += (parseFloat(line.quantity) || 0) * discountedUnitPrice;
    }

    return taxable;
}

export default function Edit({ quote, serviceCatalogItems }) {
    const { data, setData, put, processing, errors } = useForm({
        lines: quote.lines.map((line) => ({
            service_catalog_item_id: line.service_catalog_item_id,
            description: line.description,
            quantity: line.quantity,
            unit_price: line.unit_price,
            discount_percent: line.discount_percent ?? '',
            vat_rate: line.vat_rate ?? '',
            vat_exemption_reason: line.vat_exemption_reason ?? '',
        })),
    });

    const updateLine = (index, field, value) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], [field]: value };
        setData('lines', lines);
    };

    const addLine = () => setData('lines', [...data.lines, { ...EMPTY_LINE }]);

    const addFromCatalog = (catalogItemId) => {
        const item = serviceCatalogItems.find((i) => i.id === catalogItemId);
        if (!item) return;
        setData('lines', [
            ...data.lines,
            {
                service_catalog_item_id: item.id,
                description: item.name,
                quantity: 1,
                unit_price: item.base_price,
                discount_percent: '',
                vat_rate: item.default_vat_rate ?? '',
                vat_exemption_reason: item.default_vat_exemption_reason ?? '',
            },
        ]);
    };

    const removeLine = (index) =>
        setData(
            'lines',
            data.lines.filter((_, i) => i !== index),
        );

    const submit = (e) => {
        e.preventDefault();
        put(route('quotes.update', quote.id));
    };

    const total = computeTotals(data.lines);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Modifica preventivo — {quote.patient.first_name} {quote.patient.last_name}
                </h2>
            }
        >
            <Head title="Modifica preventivo" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit} className="space-y-6">
                            <div>
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                                    Righe
                                </h3>
                                <InputError message={errors.lines} className="mb-2" />

                                <div className="overflow-x-auto rounded-md border border-gray-200">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
                                            <tr>
                                                <th className="px-3 py-2">Descrizione</th>
                                                <th className="w-20 px-3 py-2">Qtà</th>
                                                <th className="w-28 px-3 py-2">Prezzo unit.</th>
                                                <th className="w-24 px-3 py-2">Sconto %</th>
                                                <th className="w-24 px-3 py-2">IVA %</th>
                                                <th className="px-3 py-2">Motivo esenzione</th>
                                                <th className="w-10 px-3 py-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.lines.map((line, index) => (
                                                <tr key={index} className="border-b border-gray-100">
                                                    <td className="px-3 py-2">
                                                        <TextInput
                                                            className="w-full"
                                                            value={line.description}
                                                            onChange={(e) => updateLine(index, 'description', e.target.value)}
                                                        />
                                                        <InputError message={errors[`lines.${index}.description`]} />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <TextInput
                                                            type="number"
                                                            step="0.01"
                                                            className="w-full"
                                                            value={line.quantity}
                                                            onChange={(e) => updateLine(index, 'quantity', e.target.value)}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <TextInput
                                                            type="number"
                                                            step="0.01"
                                                            className="w-full"
                                                            value={line.unit_price}
                                                            onChange={(e) => updateLine(index, 'unit_price', e.target.value)}
                                                        />
                                                        <InputError message={errors[`lines.${index}.unit_price`]} />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <TextInput
                                                            type="number"
                                                            step="0.01"
                                                            className="w-full"
                                                            placeholder="0"
                                                            value={line.discount_percent}
                                                            onChange={(e) => updateLine(index, 'discount_percent', e.target.value)}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <TextInput
                                                            type="number"
                                                            step="0.01"
                                                            className="w-full"
                                                            placeholder="esente"
                                                            value={line.vat_rate}
                                                            onChange={(e) => updateLine(index, 'vat_rate', e.target.value)}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <TextInput
                                                            className="w-full"
                                                            value={line.vat_exemption_reason}
                                                            onChange={(e) => updateLine(index, 'vat_exemption_reason', e.target.value)}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2 text-right">
                                                        {data.lines.length > 1 && (
                                                            <button
                                                                type="button"
                                                                onClick={() => removeLine(index)}
                                                                className="text-red-600 hover:underline"
                                                            >
                                                                ✕
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="mt-2 flex flex-wrap items-center gap-3">
                                    <SecondaryButton type="button" onClick={addLine}>
                                        Aggiungi riga vuota
                                    </SecondaryButton>
                                    <select
                                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value=""
                                        onChange={(e) => e.target.value && addFromCatalog(e.target.value)}
                                    >
                                        <option value="">Aggiungi dal listino…</option>
                                        {serviceCatalogItems.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.name} — {item.base_price} €
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <dl className="w-64 space-y-1 text-sm">
                                    <div className="flex justify-between font-semibold">
                                        <dt>Totale (provvisorio, IVA esclusa)</dt>
                                        <dd>{total.toFixed(2)} €</dd>
                                    </div>
                                </dl>
                            </div>

                            <div className="flex items-center gap-3">
                                <PrimaryButton disabled={processing}>Salva</PrimaryButton>
                                <Link href={route('quotes.show', quote.id)}>
                                    <SecondaryButton type="button">Annulla</SecondaryButton>
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
