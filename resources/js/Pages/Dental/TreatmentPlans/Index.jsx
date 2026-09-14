import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function formatDate(value) {
    if (!value) return null;
    return new Date(value).toLocaleDateString('it-IT');
}

function NewPlanForm({ patientId }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('dental.treatment-plans.store', patientId));
    };

    return (
        <form onSubmit={submit} className="mb-6 space-y-3 rounded-md bg-gray-50 p-3">
            <div>
                <InputLabel htmlFor="title" value="Titolo (facoltativo)" />
                <input
                    id="title"
                    type="text"
                    placeholder="es. Piano conservativo 2026"
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                />
                {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
            </div>
            <PrimaryButton type="submit" disabled={processing}>
                Nuovo piano di cura
            </PrimaryButton>
        </form>
    );
}

export default function Index({ patient, plans, canCreate }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Piani di cura — {patient.last_name} {patient.first_name}
                </h2>
            }
        >
            <Head title={`Piani di cura — ${patient.last_name} ${patient.first_name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                    <Link href={route('dental.show', patient.id)} className="text-sm text-indigo-600 hover:underline">
                        ← Torna alla cartella clinica
                    </Link>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        {canCreate && <NewPlanForm patientId={patient.id} />}

                        {plans.length === 0 && <p className="text-sm text-gray-400">Nessun piano di cura ancora.</p>}
                        <ul className="space-y-2">
                            {plans.map((plan) => (
                                <li key={plan.id} className="flex items-center justify-between border-b border-gray-100 pb-2 text-sm">
                                    <Link
                                        href={route('dental.treatment-plans.show', [patient.id, plan.id])}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        {plan.title || 'Piano senza titolo'}
                                    </Link>
                                    <span className="text-xs text-gray-500">
                                        {plan.items_count} {plan.items_count === 1 ? 'voce' : 'voci'} — {formatDate(plan.created_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
