import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function formatDate(value) {
    if (!value) return null;
    return new Date(value).toLocaleDateString('it-IT');
}

function GrantForm({ patientId, purposeValue, options, onDone }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        purpose: purposeValue,
        collection_method: '',
        policy_version: '',
        granted_at: new Date().toISOString().slice(0, 10),
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('patients.consents.store', patientId), {
            preserveScroll: true,
            onSuccess: () => {
                reset('collection_method', 'policy_version');
                onDone?.();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="mt-2 flex flex-wrap items-end gap-3 rounded-md bg-gray-50 p-3"
        >
            <div>
                <label className="block text-xs text-gray-500">
                    Modalità di raccolta
                </label>
                <select
                    className="mt-1 rounded-control border-ink/15 text-sm shadow-sm focus:border-brand focus:ring-brand"
                    value={data.collection_method}
                    onChange={(e) =>
                        setData('collection_method', e.target.value)
                    }
                >
                    <option value="">—</option>
                    {options.collectionMethods.map(({ value, label }) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
                {errors.collection_method && (
                    <p className="mt-1 text-xs text-red-600">
                        {errors.collection_method}
                    </p>
                )}
            </div>

            <div>
                <label className="block text-xs text-gray-500">
                    Versione informativa
                </label>
                <select
                    className="mt-1 rounded-control border-ink/15 text-sm shadow-sm focus:border-brand focus:ring-brand"
                    value={data.policy_version}
                    onChange={(e) => setData('policy_version', e.target.value)}
                >
                    <option value="">—</option>
                    {options.policyVersions.map(({ value, label }) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
                {errors.policy_version && (
                    <p className="mt-1 text-xs text-red-600">
                        {errors.policy_version}
                    </p>
                )}
            </div>

            <div>
                <label className="block text-xs text-gray-500">Data</label>
                <input
                    type="date"
                    className="mt-1 rounded-control border-ink/15 text-sm shadow-sm focus:border-brand focus:ring-brand"
                    value={data.granted_at}
                    onChange={(e) => setData('granted_at', e.target.value)}
                />
                {errors.granted_at && (
                    <p className="mt-1 text-xs text-red-600">
                        {errors.granted_at}
                    </p>
                )}
            </div>

            <PrimaryButton type="submit" disabled={processing}>
                Registra consenso
            </PrimaryButton>
        </form>
    );
}

export default function ConsentsPanel({
    patientId,
    consents,
    options,
    canManage,
}) {
    const [openPurpose, setOpenPurpose] = useState(null);

    const revoke = (consent) => {
        if (
            confirm(
                `Revocare il consenso per "${options.purposes.find((p) => p.value === consent.purpose)?.label}"? Lo storico resterà comunque conservato.`,
            )
        ) {
            router.patch(
                route('patients.consents.revoke', [
                    patientId,
                    consent.id,
                ]),
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-4 text-lg font-medium text-ink">
                Consensi
            </h3>

            <div className="space-y-4">
                {options.purposes.map(({ value, label }) => {
                    const consentsForPurpose = consents.filter(
                        (c) => c.purpose === value,
                    );
                    const active = consentsForPurpose.find(
                        (c) => c.revoked_at === null,
                    );
                    const history = consentsForPurpose.filter(
                        (c) => c.revoked_at !== null,
                    );

                    return (
                        <div
                            key={value}
                            className="border-b border-gray-100 pb-4 last:border-0"
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <span className="text-sm font-medium text-ink">
                                        {label}
                                    </span>
                                    {active ? (
                                        <span className="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">
                                            Concesso il{' '}
                                            {formatDate(active.granted_at)}
                                        </span>
                                    ) : (
                                        <span className="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                            Non concesso
                                        </span>
                                    )}
                                </div>

                                {canManage && active && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => revoke(active)}
                                    >
                                        Revoca
                                    </SecondaryButton>
                                )}

                                {canManage && !active && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() =>
                                            setOpenPurpose(
                                                openPurpose === value
                                                    ? null
                                                    : value,
                                            )
                                        }
                                    >
                                        {openPurpose === value
                                            ? 'Annulla'
                                            : 'Registra consenso'}
                                    </SecondaryButton>
                                )}
                            </div>

                            {canManage &&
                                !active &&
                                openPurpose === value && (
                                    <GrantForm
                                        patientId={patientId}
                                        purposeValue={value}
                                        options={options}
                                        onDone={() => setOpenPurpose(null)}
                                    />
                                )}

                            {history.length > 0 && (
                                <ul className="mt-2 space-y-1 text-xs text-gray-500">
                                    {history.map((c) => (
                                        <li key={c.id}>
                                            Concesso il{' '}
                                            {formatDate(c.granted_at)}, revocato
                                            il {formatDate(c.revoked_at)}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
