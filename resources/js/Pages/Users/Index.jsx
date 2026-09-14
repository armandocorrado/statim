import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ users, invitations, roles }) {
    const currentUserId = usePage().props.auth.user.id;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: roles[0] ?? '',
    });

    const submitInvite = (e) => {
        e.preventDefault();
        post(route('users.invitations.store'), {
            onSuccess: () => reset('email'),
        });
    };

    const revokeInvitation = (invitation) => {
        router.delete(route('users.invitations.destroy', invitation.id), {
            preserveScroll: true,
        });
    };

    const changeRole = (user, role) => {
        router.patch(
            route('users.role.update', user.id),
            { role },
            { preserveScroll: true },
        );
    };

    const toggleActive = (user) => {
        const routeName = user.is_active
            ? 'users.deactivate'
            : 'users.reactivate';
        router.patch(route(routeName, user.id), {}, { preserveScroll: true });
    };

    const canEditQuotePrices = (user) =>
        user.permissions.some((p) => p.name === 'treatment_plans.prices.edit');

    const toggleQuotePricePermission = (user) => {
        router.patch(
            route('users.quote-price-permission.update', user.id),
            { enabled: !canEditQuotePrices(user) },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Utenti
                </h2>
            }
        >
            <Head title="Utenti" />

            <div className="space-y-6 py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-4 text-lg font-medium text-slate-900">
                            Invita un nuovo utente
                        </h3>

                        <form
                            onSubmit={submitInvite}
                            className="flex flex-wrap items-end gap-4"
                        >
                            <div>
                                <InputLabel htmlFor="email" value="Email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    className="w-72"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel htmlFor="role" value="Ruolo" />
                                <select
                                    id="role"
                                    className="block w-48 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                    value={data.role}
                                    onChange={(e) =>
                                        setData('role', e.target.value)
                                    }
                                >
                                    {roles.map((role) => (
                                        <option key={role} value={role}>
                                            {role}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.role}
                                    className="mt-2"
                                />
                            </div>

                            <PrimaryButton
                                type="submit"
                                disabled={processing}
                            >
                                Invia invito
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                {invitations.length > 0 && (
                    <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <h3 className="p-6 pb-0 text-lg font-medium text-slate-900">
                                Inviti in attesa
                            </h3>
                            <table className="w-full text-left text-sm">
                                <thead className="border-y border-gray-200 bg-gray-50 text-gray-600">
                                    <tr>
                                        <th className="px-6 py-3">Email</th>
                                        <th className="px-6 py-3">Ruolo</th>
                                        <th className="px-6 py-3">
                                            Invitato da
                                        </th>
                                        <th className="px-6 py-3">Scade</th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {invitations.map((invitation) => (
                                        <tr
                                            key={invitation.id}
                                            className="border-b border-gray-100"
                                        >
                                            <td className="px-6 py-3">
                                                {invitation.email}
                                            </td>
                                            <td className="px-6 py-3">
                                                {invitation.role}
                                            </td>
                                            <td className="px-6 py-3">
                                                {invitation.inviter?.name ??
                                                    '—'}
                                            </td>
                                            <td className="px-6 py-3">
                                                {new Date(
                                                    invitation.expires_at,
                                                ).toLocaleDateString('it-IT')}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        revokeInvitation(
                                                            invitation,
                                                        )
                                                    }
                                                >
                                                    Revoca
                                                </SecondaryButton>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table className="w-full text-left text-sm">
                            <thead className="border-y border-gray-200 bg-gray-50 text-gray-600">
                                <tr>
                                    <th className="px-6 py-3">Nome</th>
                                    <th className="px-6 py-3">Email</th>
                                    <th className="px-6 py-3">Ruolo</th>
                                    <th className="px-6 py-3">Stato</th>
                                    <th className="px-6 py-3">Modifica prezzi preventivi</th>
                                    <th className="px-6 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b border-gray-100 hover:bg-gray-50"
                                    >
                                        <td className="px-6 py-3">
                                            {user.name}
                                        </td>
                                        <td className="px-6 py-3">
                                            {user.email}
                                        </td>
                                        <td className="px-6 py-3">
                                            <select
                                                className="rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                                defaultValue={
                                                    user.roles[0]?.name ?? ''
                                                }
                                                onChange={(e) =>
                                                    changeRole(
                                                        user,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {roles.map((role) => (
                                                    <option
                                                        key={role}
                                                        value={role}
                                                    >
                                                        {role}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-6 py-3">
                                            {user.is_active ? (
                                                <span className="rounded-full bg-green-100 px-2 py-1 text-xs text-green-700">
                                                    Attivo
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600">
                                                    Disattivato
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3">
                                            {['odontoiatra', 'igienista'].includes(user.roles[0]?.name) ? (
                                                <label className="flex items-center gap-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={canEditQuotePrices(user)}
                                                        onChange={() => toggleQuotePricePermission(user)}
                                                    />
                                                    <span className="text-xs text-gray-500">
                                                        {canEditQuotePrices(user) ? 'Abilitata' : 'Non abilitata'}
                                                    </span>
                                                </label>
                                            ) : (
                                                <span className="text-xs text-gray-300">—</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {user.id !== currentUserId && (
                                                <SecondaryButton
                                                    onClick={() =>
                                                        toggleActive(user)
                                                    }
                                                >
                                                    {user.is_active
                                                        ? 'Disattiva'
                                                        : 'Riattiva'}
                                                </SecondaryButton>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {users.links.length > 3 && (
                            <div className="flex flex-wrap gap-1 p-6">
                                {users.links.map((link, i) => (
                                    <a
                                        key={i}
                                        href={link.url ?? '#'}
                                        className={`rounded px-3 py-1 text-sm ${
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                        } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
