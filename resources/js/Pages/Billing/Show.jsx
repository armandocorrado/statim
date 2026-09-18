import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import PageHeading from '@/Components/PageHeading';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const CHANNEL_LABELS = {
    sistema_ts: 'Sistema Tessera Sanitaria',
    sdi: 'Sistema di Interscambio (fattura elettronica)',
};

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm text-gray-900">{value || '—'}</dd>
        </div>
    );
}

function formatDate(value) {
    if (!value) return null;
    return new Date(value).toLocaleDateString('it-IT');
}

export default function Show({ document }) {
    const { delete: destroy, patch, processing } = useForm();
    const isDraft = document.status === 'draft';

    const confirmDelete = (e) => {
        e.preventDefault();
        if (confirm('Eliminare questa bozza? Non è recuperabile.')) {
            destroy(route('billing.destroy', document.id));
        }
    };

    const confirmIssue = () => {
        if (
            confirm(
                'Emettere questo documento? Verrà assegnato un numero definitivo e non sarà più modificabile.',
            )
        ) {
            router.patch(route('billing.issue', document.id));
        }
    };

    const totalFromLines = document.lines.reduce(
        (sum, line) => sum + parseFloat(line.line_total),
        0,
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeading>
                    {document.document_number
                        ? `Fattura ${document.document_number}/${document.document_year}`
                        : 'Bozza fattura'}
                </PageHeading>
            }
        >
            <Head
                title={
                    document.document_number
                        ? `Fattura ${document.document_number}/${document.document_year}`
                        : 'Bozza fattura'
                }
            />

            <div className="py-16">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="bg-white p-8 shadow-soft sm:rounded-card">
                        <dl className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <Field
                                label="Stato"
                                value={isDraft ? 'Bozza' : 'Emesso'}
                            />
                            <Field
                                label="Data emissione"
                                value={formatDate(document.issued_at)}
                            />
                            <Field
                                label="Paziente"
                                value={
                                    <Link
                                        href={route(
                                            'patients.show',
                                            document.patient.id,
                                        )}
                                        className="text-brand hover:underline"
                                    >
                                        {document.patient.first_name}{' '}
                                        {document.patient.last_name}
                                    </Link>
                                }
                            />
                            <Field
                                label="Intestatario"
                                value={document.recipient_name}
                            />
                            {document.source_quote && (
                                <Field
                                    label="Generato dal preventivo"
                                    value={
                                        <Link
                                            href={route(
                                                'quotes.show',
                                                document.source_quote.id,
                                            )}
                                            className="text-brand hover:underline"
                                        >
                                            Preventivo del{' '}
                                            {formatDate(
                                                document.source_quote
                                                    .issued_at,
                                            )}
                                        </Link>
                                    }
                                />
                            )}
                            {!isDraft && (
                                <Field
                                    label="Canale fiscale"
                                    value={
                                        CHANNEL_LABELS[document.fiscal_channel]
                                    }
                                />
                            )}
                            {!isDraft && (
                                <Field
                                    label="Riferimento invio (mock)"
                                    value={document.external_reference}
                                />
                            )}
                        </dl>

                        <div className="mt-8">
                            <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Righe
                            </h3>
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
                                    <tr>
                                        <th className="px-3 py-2">
                                            Descrizione
                                        </th>
                                        <th className="px-3 py-2">Qtà</th>
                                        <th className="px-3 py-2">
                                            Prezzo unit.
                                        </th>
                                        <th className="px-3 py-2">IVA</th>
                                        <th className="px-3 py-2">Totale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {document.lines.map((line) => (
                                        <tr
                                            key={line.id}
                                            className="border-b border-gray-100"
                                        >
                                            <td className="px-3 py-2">
                                                {line.description}
                                            </td>
                                            <td className="px-3 py-2">
                                                {line.quantity}
                                            </td>
                                            <td className="px-3 py-2">
                                                {line.unit_price} €
                                            </td>
                                            <td className="px-3 py-2">
                                                {line.vat_rate
                                                    ? `${line.vat_rate}%`
                                                    : line.vat_exemption_reason
                                                      ? 'Esente'
                                                      : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {line.line_total} €
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <div className="mt-4 flex justify-end">
                                <dl className="w-64 space-y-1 text-sm">
                                    {isDraft ? (
                                        <div className="flex justify-between font-semibold">
                                            <dt>Totale (provvisorio)</dt>
                                            <dd>
                                                {totalFromLines.toFixed(2)} €
                                            </dd>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="flex justify-between">
                                                <dt className="text-gray-500">
                                                    Imponibile
                                                </dt>
                                                <dd>
                                                    {document.total_taxable} €
                                                </dd>
                                            </div>
                                            <div className="flex justify-between">
                                                <dt className="text-gray-500">
                                                    IVA
                                                </dt>
                                                <dd>{document.total_vat} €</dd>
                                            </div>
                                            <div className="flex justify-between font-semibold">
                                                <dt>Totale</dt>
                                                <dd>
                                                    {document.total_amount} €
                                                </dd>
                                            </div>
                                        </>
                                    )}
                                </dl>
                            </div>
                        </div>

                        <div className="mt-8 flex items-center gap-3">
                            <Link href={route('billing.index')}>
                                <SecondaryButton type="button">
                                    Torna alla lista
                                </SecondaryButton>
                            </Link>
                            {isDraft && (
                                <>
                                    <Link
                                        href={route(
                                            'billing.edit',
                                            document.id,
                                        )}
                                        className="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark"
                                    >
                                        Modifica
                                    </Link>
                                    <PrimaryButton
                                        type="button"
                                        disabled={processing}
                                        onClick={confirmIssue}
                                    >
                                        Emetti
                                    </PrimaryButton>
                                    <button
                                        onClick={confirmDelete}
                                        disabled={processing}
                                        className="ml-auto text-sm text-red-600 hover:underline"
                                    >
                                        Elimina bozza
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
