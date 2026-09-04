import PatientFormFields from '@/Components/PatientFormFields';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        first_name: '',
        last_name: '',
        date_of_birth: '',
        gender: '',
        birth_place: '',
        fiscal_code: '',
        email: '',
        mobile_phone: '',
        landline_phone: '',
        address_street: '',
        address_postal_code: '',
        address_city: '',
        address_province: '',
        residence_street: '',
        residence_postal_code: '',
        residence_city: '',
        residence_province: '',
        vat_number: '',
        source: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('patients.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Nuovo paziente
                </h2>
            }
        >
            <Head title="Nuovo paziente" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit} className="space-y-6">
                            <PatientFormFields
                                data={data}
                                setData={setData}
                                errors={errors}
                            />

                            <div className="flex items-center gap-3">
                                <PrimaryButton disabled={processing}>
                                    Salva
                                </PrimaryButton>
                                <Link href={route('patients.index')}>
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
