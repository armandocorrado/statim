import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const STATUS_STYLES = {
    draft: 'bg-gray-100 text-gray-700',
    issued: 'bg-blue-100 text-blue-700',
    accepted: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    in_progress: 'bg-amber-100 text-amber-700',
    completed: 'bg-teal-100 text-teal-700',
};

const STATUS_LABELS = {
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

export default function Index({ quotes, acceptanceRate }) {
    const canManageCatalog = usePage().props.auth.permissions.includes('treatment_plans.administer');

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Preventivi
                </h2>
            }
        >
            <Head title="Preventivi" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex justify-end">
                        <Link href={route('service-catalog.index')} className="text-sm text-indigo-600 hover:underline">
                            {canManageCatalog ? 'Gestisci listino prestazioni' : 'Vedi listino prestazioni'}
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="bg-white p-4 shadow-sm sm:rounded-lg">
                            <dt className="text-xs uppercase tracking-wide text-gray-500">Preventivi emessi</dt>
                            <dd className="mt-1 text-2xl font-semibold text-slate-900">{acceptanceRate.issued}</dd>
                        </div>
                        <div className="bg-white p-4 shadow-sm sm:rounded-lg">
                            <dt className="text-xs uppercase tracking-wide text-gray-500">Accettati</dt>
                            <dd className="mt-1 text-2xl font-semibold text-slate-900">{acceptanceRate.accepted}</dd>
                        </div>
                        <div className="bg-white p-4 shadow-sm sm:rounded-lg">
                            <dt className="text-xs uppercase tracking-wide text-gray-500">Tasso di accettazione</dt>
                            <dd className="mt-1 text-2xl font-semibold text-slate-900">
                                {acceptanceRate.rate !== null ? `${acceptanceRate.rate}%` : '—'}
                            </dd>
                        </div>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table className="w-full text-left text-sm">
                            <thead className="border-y border-gray-200 bg-gray-50 text-gray-600">
                                <tr>
                                    <th className="px-6 py-3">Paziente</th>
                                    <th className="px-6 py-3">Stato</th>
                                    <th className="px-6 py-3">Data emissione</th>
                                    <th className="px-6 py-3">Totale</th>
                                </tr>
                            </thead>
                            <tbody>
                                {quotes.data.map((quote) => (
                                    <tr key={quote.id} className="border-b border-gray-100 hover:bg-gray-50">
                                        <td className="px-6 py-3">
                                            <Link
                                                href={route('quotes.show', quote.id)}
                                                className="text-indigo-600 hover:underline"
                                            >
                                                {quote.patient.first_name} {quote.patient.last_name}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-3">
                                            <span className={`rounded-full px-2 py-1 text-xs ${STATUS_STYLES[quote.status]}`}>
                                                {STATUS_LABELS[quote.status]}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3">{formatDate(quote.issued_at) ?? '—'}</td>
                                        <td className="px-6 py-3">
                                            {quote.total_amount ? `${quote.total_amount} €` : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {quotes.links.length > 3 && (
                            <div className="flex flex-wrap gap-1 p-6">
                                {quotes.links.map((link, i) => (
                                    <a
                                        key={i}
                                        href={link.url ?? '#'}
                                        className={`rounded px-3 py-1 text-sm ${
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                        } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
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
