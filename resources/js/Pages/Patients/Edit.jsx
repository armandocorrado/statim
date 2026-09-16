import PatientFormFields from '@/Components/PatientFormFields';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import PageHeading from '@/Components/PageHeading';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ patient }) {
    const { data, setData, put, processing, errors } = useForm({
        first_name: patient.first_name ?? '',
        last_name: patient.last_name ?? '',
        date_of_birth: patient.date_of_birth ?? '',
        gender: patient.gender ?? '',
        birth_place: patient.birth_place ?? '',
        fiscal_code: patient.fiscal_code ?? '',
        email: patient.email ?? '',
        mobile_phone: patient.mobile_phone ?? '',
        landline_phone: patient.landline_phone ?? '',
        address_street: patient.address_street ?? '',
        address_postal_code: patient.address_postal_code ?? '',
        address_city: patient.address_city ?? '',
        address_province: patient.address_province ?? '',
        residence_street: patient.residence_street ?? '',
        residence_postal_code: patient.residence_postal_code ?? '',
        residence_city: patient.residence_city ?? '',
        residence_province: patient.residence_province ?? '',
        vat_number: patient.vat_number ?? '',
        source: patient.source ?? '',
        notes: patient.notes ?? '',
        is_active: patient.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('patients.update', patient.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeading>
                    Modifica paziente
                </PageHeading>
            }
        >
            <Head title="Modifica paziente" />

            <div className="py-16">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-8 shadow-soft sm:rounded-card">
                        <form onSubmit={submit} className="space-y-6">
                            <PatientFormFields
                                data={data}
                                setData={setData}
                                errors={errors}
                            />

                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) =>
                                        setData(
                                            'is_active',
                                            e.target.checked,
                                        )
                                    }
                                    className="rounded border-ink/15 text-brand shadow-sm focus:ring-brand"
                                />
                                <span className="text-sm text-gray-700">
                                    Paziente attivo
                                </span>
                            </label>

                            <div className="flex items-center gap-3">
                                <PrimaryButton disabled={processing}>
                                    Salva
                                </PrimaryButton>
                                <Link
                                    href={route('patients.show', patient.id)}
                                >
                                    <SecondaryButton type="button">
                                        Annulla
                                    </SecondaryButton>
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
