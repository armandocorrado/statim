import ConsentsPanel from '@/Components/ConsentsPanel';
import SecondaryButton from '@/Components/SecondaryButton';
import PageHeading from '@/Components/PageHeading';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm text-gray-900">{value || '—'}</dd>
        </div>
    );
}

const PATIENT_SOURCE_LABELS = {
    passaparola: 'Passaparola',
    campagna_social: 'Campagna social',
    google: 'Google',
    sito: 'Sito web',
    invio_medico: 'Invio da medico',
    altro: 'Altro',
};

function formatAddress(street, postalCode, city, province) {
    const parts = [street, [postalCode, city].filter(Boolean).join(' '), province]
        .filter(Boolean);

    return parts.length ? parts.join(', ') : null;
}

export default function Show({ patient, consentOptions }) {
    const { delete: destroy, processing } = useForm();
    const permissions = usePage().props.auth.permissions;
    const canManageConsents = permissions.includes('consents.manage');
    const canViewClinicalRecord =
        permissions.includes('clinical_records.view') ||
        permissions.includes('clinical_records.hygiene.view');

    const confirmDelete = (e) => {
        e.preventDefault();
        if (
            confirm(
                'Disattivare questo paziente? Il record resta conservato ma non sarà più visibile nelle liste.',
            )
        ) {
            destroy(route('patients.destroy', patient.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeading>
                    {patient.last_name} {patient.first_name}
                </PageHeading>
            }
        >
            <Head title={`${patient.last_name} ${patient.first_name}`} />

            <div className="py-16">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-8 shadow-soft sm:rounded-card">
                        <dl className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <Field label="Cognome" value={patient.last_name} />
                            <Field label="Nome" value={patient.first_name} />
                            <Field
                                label="Data di nascita"
                                value={patient.date_of_birth}
                            />
                            <Field label="Sesso" value={patient.gender} />
                            <Field label="Luogo di nascita" value={patient.birth_place} />
                            <Field
                                label="Codice fiscale"
                                value={patient.fiscal_code}
                            />
                            <Field label="Email" value={patient.email} />
                            <Field label="Cellulare" value={patient.mobile_phone} />
                            <Field label="Telefono fisso" value={patient.landline_phone} />
                            <Field
                                label="Domicilio"
                                value={formatAddress(
                                    patient.address_street,
                                    patient.address_postal_code,
                                    patient.address_city,
                                    patient.address_province,
                                )}
                            />
                            <Field
                                label="Residenza"
                                value={formatAddress(
                                    patient.residence_street,
                                    patient.residence_postal_code,
                                    patient.residence_city,
                                    patient.residence_province,
                                )}
                            />
                            <Field label="Partita IVA" value={patient.vat_number} />
                            <Field
                                label="Fonte"
                                value={
                                    patient.source
                                        ? PATIENT_SOURCE_LABELS[patient.source]
                                        : null
                                }
                            />
                            {patient.guardian && (
                                <Field
                                    label="Tutore/referente"
                                    value={`${patient.guardian.first_name} ${patient.guardian.last_name}${patient.guardian_relationship ? ` (${patient.guardian_relationship})` : ''}`}
                                />
                            )}
                            <div className="sm:col-span-2">
                                <Field label="Note" value={patient.notes} />
                            </div>
                            <Field
                                label="Stato"
                                value={
                                    patient.is_active
                                        ? 'Attivo'
                                        : 'Disattivato'
                                }
                            />
                        </dl>

                        <div className="mt-8 flex items-center gap-3">
                            <Link
                                href={route('patients.edit', patient.id)}
                                className="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark"
                            >
                                Modifica
                            </Link>
                            {canViewClinicalRecord && (
                                <Link
                                    href={route('dental.show', patient.id)}
                                    className="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
                                >
                                    Cartella clinica
                                </Link>
                            )}
                            <Link href={route('patients.index')}>
                                <SecondaryButton type="button">
                                    Torna alla lista
                                </SecondaryButton>
                            </Link>
                            {patient.is_active && (
                                <button
                                    onClick={confirmDelete}
                                    disabled={processing}
                                    className="ml-auto text-sm text-red-600 hover:underline"
                                >
                                    Disattiva paziente
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                <div className="mx-auto mt-6 max-w-3xl sm:px-6 lg:px-8">
                    <ConsentsPanel
                        patientId={patient.id}
                        consents={patient.consents ?? []}
                        options={consentOptions}
                        canManage={canManageConsents}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
