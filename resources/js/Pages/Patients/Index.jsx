import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ patients, filters }) {
    const submitSearch = (e) => {
        e.preventDefault();
        router.get(
            route('patients.index'),
            { search: e.target.search.value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Pazienti
                </h2>
            }
        >
            <Head title="Pazienti" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="flex items-center justify-between gap-4 p-6">
                            <form
                                onSubmit={submitSearch}
                                className="flex items-center gap-2"
                            >
                                <TextInput
                                    name="search"
                                    defaultValue={filters?.search ?? ''}
                                    placeholder="Cerca per nome o cognome..."
                                    className="w-72"
                                />
                                <PrimaryButton type="submit">
                                    Cerca
                                </PrimaryButton>
                            </form>

                            <Link
                                href={route('patients.create')}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Nuovo paziente
                            </Link>
                        </div>

                        <table className="w-full text-left text-sm">
                            <thead className="border-y border-gray-200 bg-gray-50 text-gray-600">
                                <tr>
                                    <th className="px-6 py-3">Cognome</th>
                                    <th className="px-6 py-3">Nome</th>
                                    <th className="px-6 py-3">
                                        Data di nascita
                                    </th>
                                    <th className="px-6 py-3">Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                {patients.data.map((patient) => (
                                    <tr
                                        key={patient.id}
                                        className="border-b border-gray-100 hover:bg-gray-50"
                                    >
                                        <td className="px-6 py-3">
                                            <Link
                                                href={route(
                                                    'patients.show',
                                                    patient.id,
                                                )}
                                                className="text-indigo-600 hover:underline"
                                            >
                                                {patient.last_name}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-3">
                                            {patient.first_name}
                                        </td>
                                        <td className="px-6 py-3">
                                            {patient.date_of_birth ?? '—'}
                                        </td>
                                        <td className="px-6 py-3">
                                            {patient.is_active ? (
                                                <span className="rounded-full bg-green-100 px-2 py-1 text-xs text-green-700">
                                                    Attivo
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600">
                                                    Disattivato
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}

                                {patients.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-6 py-8 text-center text-gray-500"
                                        >
                                            Nessun paziente trovato.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        {patients.links.length > 3 && (
                            <div className="flex flex-wrap gap-1 p-6">
                                {patients.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url ?? '#'}
                                        preserveScroll
                                        className={`rounded px-3 py-1 text-sm ${
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                        } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
