import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

function AddItemForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        category: '',
        base_price: '',
        default_vat_rate: '',
        default_vat_exemption_reason: 'art. 10 n. 18 DPR 633/72',
        default_duration_minutes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('service-catalog.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name', 'base_price'),
        });
    };

    return (
        <form onSubmit={submit} className="mb-6 space-y-3 rounded-md bg-gray-50 p-3">
            <div className="flex flex-wrap gap-3">
                <div>
                    <InputLabel htmlFor="name" value="Nome" />
                    <input
                        id="name"
                        type="text"
                        className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>
                <div>
                    <InputLabel htmlFor="category" value="Categoria (facoltativa)" />
                    <input
                        id="category"
                        type="text"
                        placeholder="es. general, hygiene"
                        className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="base_price" value="Prezzo base" />
                    <input
                        id="base_price"
                        type="number"
                        step="0.01"
                        className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.base_price}
                        onChange={(e) => setData('base_price', e.target.value)}
                    />
                    {errors.base_price && <p className="mt-1 text-xs text-red-600">{errors.base_price}</p>}
                </div>
                <div>
                    <InputLabel htmlFor="default_vat_rate" value="IVA % (vuoto = esente)" />
                    <input
                        id="default_vat_rate"
                        type="number"
                        step="0.01"
                        className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.default_vat_rate}
                        onChange={(e) => setData('default_vat_rate', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="default_duration_minutes" value="Durata (min)" />
                    <input
                        id="default_duration_minutes"
                        type="number"
                        className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.default_duration_minutes}
                        onChange={(e) => setData('default_duration_minutes', e.target.value)}
                    />
                </div>
            </div>
            <SecondaryButton type="submit" disabled={processing}>
                Aggiungi voce
            </SecondaryButton>
        </form>
    );
}

function EditableRow({ item, canManage }) {
    const { data, setData, put, processing } = useForm({
        name: item.name,
        category: item.category ?? '',
        base_price: item.base_price,
        default_vat_rate: item.default_vat_rate ?? '',
        default_vat_exemption_reason: item.default_vat_exemption_reason ?? '',
        default_duration_minutes: item.default_duration_minutes ?? '',
        is_active: item.is_active,
    });

    const save = () => {
        put(route('service-catalog.update', item.id), { preserveScroll: true });
    };

    if (!canManage) {
        return (
            <tr className="border-b border-gray-100">
                <td className="px-3 py-2">{item.name}</td>
                <td className="px-3 py-2">{item.category ?? '—'}</td>
                <td className="px-3 py-2">{item.base_price} €</td>
                <td className="px-3 py-2">{item.default_vat_rate ? `${item.default_vat_rate}%` : 'Esente'}</td>
                <td className="px-3 py-2">{item.is_active ? 'Attivo' : 'Disattivato'}</td>
            </tr>
        );
    }

    return (
        <tr className="border-b border-gray-100">
            <td className="px-3 py-2">
                <input
                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
            </td>
            <td className="px-3 py-2">
                <input
                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.category}
                    onChange={(e) => setData('category', e.target.value)}
                />
            </td>
            <td className="px-3 py-2">
                <input
                    type="number"
                    step="0.01"
                    className="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.base_price}
                    onChange={(e) => setData('base_price', e.target.value)}
                />
            </td>
            <td className="px-3 py-2">
                <input
                    type="number"
                    step="0.01"
                    placeholder="esente"
                    className="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.default_vat_rate}
                    onChange={(e) => setData('default_vat_rate', e.target.value)}
                />
            </td>
            <td className="px-3 py-2">
                <label className="flex items-center gap-1 text-xs">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    Attivo
                </label>
            </td>
            <td className="px-3 py-2">
                <SecondaryButton type="button" disabled={processing} onClick={save}>
                    Salva
                </SecondaryButton>
            </td>
        </tr>
    );
}

export default function Index({ items, canManage }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Listino prestazioni
                </h2>
            }
        >
            <Head title="Listino prestazioni" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        {canManage && <AddItemForm />}

                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
                                <tr>
                                    <th className="px-3 py-2">Nome</th>
                                    <th className="px-3 py-2">Categoria</th>
                                    <th className="px-3 py-2">Prezzo</th>
                                    <th className="px-3 py-2">IVA</th>
                                    <th className="px-3 py-2">Stato</th>
                                    {canManage && <th className="px-3 py-2" />}
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <EditableRow key={item.id} item={item} canManage={canManage} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
