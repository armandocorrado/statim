import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import PageHeading from '@/Components/PageHeading';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

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

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm text-gray-900">{value || '—'}</dd>
        </div>
    );
}

export default function Show({
    quote,
    canManage,
    canIssue,
    canDelete,
    canTransition,
    allowedNextStatuses,
    canGenerateBillingDocument,
    billingDocuments,
}) {
    const { delete: destroy, patch, processing } = useForm();
    const isDraft = quote.status === 'draft';

    const confirmGenerateBillingDocument = () => {
        if (
            confirm(
                'Generare un documento fiscale da questo preventivo? Verrà creata una bozza da rivedere ed emettere separatamente.',
            )
        ) {
            router.post(route('quotes.generate-billing-document', quote.id));
        }
    };

    const confirmDelete = (e) => {
        e.preventDefault();
        if (confirm('Eliminare questa bozza di preventivo? Non è recuperabile.')) {
            destroy(route('quotes.destroy', quote.id));
        }
    };

    const confirmIssue = () => {
        if (confirm('Emettere questo preventivo? I totali verranno congelati e non sarà più modificabile.')) {
            router.patch(route('quotes.issue', quote.id));
        }
    };

    const transitionTo = (status) => {
        router.patch(route('quotes.status.update', quote.id), { status });
    };

    const totalFromLines = quote.lines.reduce((sum, line) => sum + parseFloat(line.line_total), 0);

    return (
        <AuthenticatedLayout
            header={
                <PageHeading>
                    Preventivo — {quote.patient.first_name} {quote.patient.last_name}
                </PageHeading>
            }
        >
            <Head title="Preventivo" />

            <div className="py-16">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="bg-white p-8 shadow-soft sm:rounded-card">
                        <dl className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <Field label="Stato" value={STATUS_LABELS[quote.status]} />
                            <Field label="Data emissione" value={formatDate(quote.issued_at)} />
                            <Field label="Data accettazione/rifiuto" value={formatDate(quote.responded_at)} />
                            <Field
                                label="Paziente"
                                value={
                                    <Link
                                        href={route('patients.show', quote.patient.id)}
                                        className="text-brand hover:underline"
                                    >
                                        {quote.patient.first_name} {quote.patient.last_name}
                                    </Link>
                                }
                            />
                        </dl>

                        <div className="mt-8">
                            <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Righe
                            </h3>
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
                                    <tr>
                                        <th className="px-3 py-2">Descrizione</th>
                                        <th className="px-3 py-2">Qtà</th>
                                        <th className="px-3 py-2">Prezzo unit.</th>
                                        <th className="px-3 py-2">Sconto</th>
                                        <th className="px-3 py-2">IVA</th>
                                        <th className="px-3 py-2">Totale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {quote.lines.map((line) => (
                                        <tr key={line.id} className="border-b border-gray-100">
                                            <td className="px-3 py-2">{line.description}</td>
                                            <td className="px-3 py-2">{line.quantity}</td>
                                            <td className="px-3 py-2">{line.unit_price} €</td>
                                            <td className="px-3 py-2">
                                                {line.discount_percent ? `${line.discount_percent}%` : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {line.vat_rate
                                                    ? `${line.vat_rate}%`
                                                    : line.vat_exemption_reason
                                                      ? 'Esente'
                                                      : '—'}
                                            </td>
                                            <td className="px-3 py-2">{line.line_total} €</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <div className="mt-4 flex justify-end">
                                <dl className="w-64 space-y-1 text-sm">
                                    {isDraft ? (
                                        <div className="flex justify-between font-semibold">
                                            <dt>Totale (provvisorio)</dt>
                                            <dd>{totalFromLines.toFixed(2)} €</dd>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="flex justify-between">
                                                <dt className="text-gray-500">Imponibile</dt>
                                                <dd>{quote.total_taxable} €</dd>
                                            </div>
                                            <div className="flex justify-between">
                                                <dt className="text-gray-500">IVA</dt>
                                                <dd>{quote.total_vat} €</dd>
                                            </div>
                                            <div className="flex justify-between font-semibold">
                                                <dt>Totale</dt>
                                                <dd>{quote.total_amount} €</dd>
                                            </div>
                                        </>
                                    )}
                                </dl>
                            </div>
                        </div>

                        {billingDocuments.length > 0 && (
                            <div className="mt-8">
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                                    Documenti fiscali generati
                                </h3>
                                <ul className="space-y-1 text-sm">
                                    {billingDocuments.map((document) => (
                                        <li key={document.id}>
                                            <Link
                                                href={route('billing.show', document.id)}
                                                className="text-brand hover:underline"
                                            >
                                                {document.document_number
                                                    ? `Documento ${document.document_number}/${document.document_year}`
                                                    : `Bozza del ${formatDate(document.created_at)}`}
                                            </Link>{' '}
                                            {document.total_amount && (
                                                <span className="text-gray-500">— {document.total_amount} €</span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="mt-8 flex flex-wrap items-center gap-3">
                            <Link href={route('quotes.index')}>
                                <SecondaryButton type="button">Torna alla lista</SecondaryButton>
                            </Link>

                            {canGenerateBillingDocument && (
                                <SecondaryButton type="button" onClick={confirmGenerateBillingDocument}>
                                    Genera documento fiscale
                                </SecondaryButton>
                            )}

                            {canManage && isDraft && (
                                <Link
                                    href={route('quotes.edit', quote.id)}
                                    className="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark"
                                >
                                    Modifica prezzi
                                </Link>
                            )}
                            {canIssue && isDraft && (
                                <PrimaryButton type="button" disabled={processing} onClick={confirmIssue}>
                                    Emetti
                                </PrimaryButton>
                            )}
                            {canDelete && isDraft && (
                                <button
                                    onClick={confirmDelete}
                                    disabled={processing}
                                    className="ml-auto text-sm text-red-600 hover:underline"
                                >
                                    Elimina bozza
                                </button>
                            )}

                            {canTransition &&
                                allowedNextStatuses.map((status) => (
                                    <SecondaryButton
                                        key={status}
                                        type="button"
                                        onClick={() => transitionTo(status)}
                                    >
                                        Segna come {STATUS_LABELS[status]}
                                    </SecondaryButton>
                                ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
