import PrimaryButton from '@/Components/PrimaryButton';
import PageHeading from '@/Components/PageHeading';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const STATUS_STYLES = {
    draft: 'bg-gray-100 text-gray-700',
    issued: 'bg-green-100 text-green-700',
};

const STATUS_LABELS = {
    draft: 'Bozza',
    issued: 'Emesso',
};

function formatDate(value) {
    if (!value) return null;
    return new Date(value).toLocaleDateString('it-IT');
}

export default function Index({ documents }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeading>
                    Fatturazione
                </PageHeading>
            }
        >
            <Head title="Fatturazione" />

            <div className="py-16">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <div className="mb-4 flex justify-end">
                        <Link href={route('billing.create')}>
                            <PrimaryButton>Nuova bozza</PrimaryButton>
                        </Link>
                    </div>

                    <div className="overflow-hidden bg-white shadow-soft sm:rounded-card">
                        <table className="w-full text-left text-sm">
                            <thead className="border-y border-cream-dark bg-cream text-ink-secondary">
                                <tr>
                                    <th className="px-6 py-3">Numero</th>
                                    <th className="px-6 py-3">Data</th>
                                    <th className="px-6 py-3">Paziente</th>
                                    <th className="px-6 py-3">Stato</th>
                                    <th className="px-6 py-3">Totale</th>
                                </tr>
                            </thead>
                            <tbody>
                                {documents.data.map((document) => (
                                    <tr
                                        key={document.id}
                                        className="border-b border-gray-100 hover:bg-cream"
                                    >
                                        <td className="px-6 py-3">
                                            <Link
                                                href={route(
                                                    'billing.show',
                                                    document.id,
                                                )}
                                                className="text-brand hover:underline"
                                            >
                                                {document.document_number
                                                    ? `${document.document_number}/${document.document_year}`
                                                    : 'Bozza'}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-3">
                                            {formatDate(document.issued_at) ?? '—'}
                                        </td>
                                        <td className="px-6 py-3">
                                            {document.patient.first_name}{' '}
                                            {document.patient.last_name}
                                        </td>
                                        <td className="px-6 py-3">
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs ${STATUS_STYLES[document.status]}`}
                                            >
                                                {STATUS_LABELS[document.status]}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3">
                                            {document.total_amount
                                                ? `${document.total_amount} €`
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {documents.links.length > 3 && (
                            <div className="flex flex-wrap gap-1 p-6">
                                {documents.links.map((link, i) => (
                                    <a
                                        key={i}
                                        href={link.url ?? '#'}
                                        className={`rounded px-3 py-1 text-sm ${
                                            link.active
                                                ? 'bg-brand text-white'
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
