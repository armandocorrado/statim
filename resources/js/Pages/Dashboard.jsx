import PageHeading from '@/Components/PageHeading';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <PageHeading>
                    Dashboard
                </PageHeading>
            }
        >
            <Head title="Dashboard" />

            <div className="py-16">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-soft sm:rounded-card">
                        <div className="p-6 text-gray-900">
                            Accesso effettuato. Vai alla sezione{' '}
                            <a
                                href={route('patients.index')}
                                className="font-medium text-brand hover:underline"
                            >
                                Pazienti
                            </a>
                            {' '}per gestire l'anagrafica.
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
