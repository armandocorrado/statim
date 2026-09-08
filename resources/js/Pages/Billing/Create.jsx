import BillingDocumentFormFields from '@/Components/BillingDocumentFormFields';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        patient_id: null,
        recipient_patient_id: null,
        lines: [
            {
                description: '',
                quantity: 1,
                unit_price: '',
                vat_rate: '',
                vat_exemption_reason:
                    'Art. 10 n. 18 DPR 633/72 - prestazione sanitaria',
            },
        ],
    });

    const [patientLabel, setPatientLabel] = useState('');
    const [recipientLabel, setRecipientLabel] = useState('');

    const submit = (e) => {
        e.preventDefault();
        post(route('billing.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Nuova bozza fattura
                </h2>
            }
        >
            <Head title="Nuova bozza fattura" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit} className="space-y-6">
                            <BillingDocumentFormFields
                                data={data}
                                setData={setData}
                                errors={errors}
                                patientLabel={patientLabel}
                                setPatientLabel={setPatientLabel}
                                recipientLabel={recipientLabel}
                                setRecipientLabel={setRecipientLabel}
                            />

                            <div className="flex items-center gap-3">
                                <PrimaryButton disabled={processing}>
                                    Salva bozza
                                </PrimaryButton>
                                <Link href={route('billing.index')}>
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
