import PatientFormFields from '@/Components/PatientFormFields';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ patient }) {
    const { data, setData, put, processing, errors } = useForm({
        first_name: patient.first_name ?? '',
        last_name: patient.last_name ?? '',
        date_of_birth: patient.date_of_birth ?? '',
        gender: patient.gender ?? '',
        fiscal_code: patient.fiscal_code ?? '',
        email: patient.email ?? '',
        phone: patient.phone ?? '',
        address: patient.address ?? '',
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
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Modifica paziente
                </h2>
            }
        >
            <Head title="Modifica paziente" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
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
                                    className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
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
