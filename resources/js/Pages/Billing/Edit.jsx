import BillingDocumentFormFields from '@/Components/BillingDocumentFormFields';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Edit({ document }) {
    const { data, setData, put, processing, errors } = useForm({
        patient_id: document.patient.id,
        recipient_patient_id: document.recipient?.id ?? null,
        lines: document.lines.map((line) => ({
            description: line.description,
            quantity: line.quantity,
            unit_price: line.unit_price,
            vat_rate: line.vat_rate,
            vat_exemption_reason: line.vat_exemption_reason,
        })),
    });

    const [patientLabel, setPatientLabel] = useState(
        `${document.patient.first_name} ${document.patient.last_name}`,
    );
    const [recipientLabel, setRecipientLabel] = useState(
        document.recipient
            ? `${document.recipient.first_name} ${document.recipient.last_name}`
            : '',
    );

    const submit = (e) => {
        e.preventDefault();
        put(route('billing.update', document.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Modifica bozza
                </h2>
            }
        >
            <Head title="Modifica bozza fattura" />

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
                                    Salva
                                </PrimaryButton>
                                <Link
                                    href={route('billing.show', document.id)}
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
