import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm text-gray-900">{value || '—'}</dd>
        </div>
    );
}

export default function Show({ patient }) {
    const { delete: destroy, processing } = useForm();

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
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {patient.last_name} {patient.first_name}
                </h2>
            }
        >
            <Head title={`${patient.last_name} ${patient.first_name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <dl className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <Field label="Cognome" value={patient.last_name} />
                            <Field label="Nome" value={patient.first_name} />
                            <Field
                                label="Data di nascita"
                                value={patient.date_of_birth}
                            />
                            <Field label="Sesso" value={patient.gender} />
                            <Field
                                label="Codice fiscale"
                                value={patient.fiscal_code}
                            />
                            <Field label="Email" value={patient.email} />
                            <Field label="Telefono" value={patient.phone} />
                            <Field label="Indirizzo" value={patient.address} />
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
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Modifica
                            </Link>
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
            </div>
        </AuthenticatedLayout>
    );
}
