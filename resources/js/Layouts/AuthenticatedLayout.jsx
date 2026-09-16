import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import medcareLogoWhite from '../../images/logo-white.png';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const { user, permissions } = usePage().props.auth;
    const canViewUsers = permissions?.includes('users.view');
    const canViewAgenda =
        permissions?.includes('agenda.view.own') ||
        permissions?.includes('agenda.view.all');
    const canViewBilling = permissions?.includes('billing.view');
    const canViewQuotes = permissions?.includes('treatment_plans.view');

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="min-h-screen bg-surface">
            <nav className="bg-brand">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center gap-2">
                                <Link href="/" className="flex items-center gap-2">
                                    <img
                                        src={medcareLogoWhite}
                                        alt="MedCare"
                                        className="h-9 w-9 object-contain"
                                    />
                                    <span className="font-serif text-lg font-semibold text-white">
                                        MedCare
                                    </span>
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    href={route('patients.index')}
                                    active={route().current('patients.*')}
                                >
                                    Pazienti
                                </NavLink>
                                {canViewAgenda && (
                                    <NavLink
                                        href={route('agenda.index')}
                                        active={route().current('agenda.*')}
                                    >
                                        Agenda
                                    </NavLink>
                                )}
                                {canViewBilling && (
                                    <NavLink
                                        href={route('billing.index')}
                                        active={route().current('billing.*')}
                                    >
                                        Fatturazione
                                    </NavLink>
                                )}
                                {canViewQuotes && (
                                    <NavLink
                                        href={route('quotes.index')}
                                        active={route().current('quotes.*') || route().current('service-catalog.*')}
                                    >
                                        Preventivi
                                    </NavLink>
                                )}
                                {canViewUsers && (
                                    <NavLink
                                        href={route('users.index')}
                                        active={route().current('users.*')}
                                    >
                                        Utenti
                                    </NavLink>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-control">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-control border border-transparent px-3 py-2 text-sm font-medium leading-4 text-white/80 transition duration-150 ease-in-out hover:text-white focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profilo
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Esci
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-control p-2 text-white/80 transition duration-150 ease-in-out hover:bg-brand-dark hover:text-white focus:bg-brand-dark focus:text-white focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' border-t border-brand-dark bg-white sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('patients.index')}
                            active={route().current('patients.*')}
                        >
                            Pazienti
                        </ResponsiveNavLink>
                        {canViewAgenda && (
                            <ResponsiveNavLink
                                href={route('agenda.index')}
                                active={route().current('agenda.*')}
                            >
                                Agenda
                            </ResponsiveNavLink>
                        )}
                        {canViewBilling && (
                            <ResponsiveNavLink
                                href={route('billing.index')}
                                active={route().current('billing.*')}
                            >
                                Fatturazione
                            </ResponsiveNavLink>
                        )}
                        {canViewQuotes && (
                            <ResponsiveNavLink
                                href={route('quotes.index')}
                                active={route().current('quotes.*') || route().current('service-catalog.*')}
                            >
                                Preventivi
                            </ResponsiveNavLink>
                        )}
                        {canViewUsers && (
                            <ResponsiveNavLink
                                href={route('users.index')}
                                active={route().current('users.*')}
                            >
                                Utenti
                            </ResponsiveNavLink>
                        )}
                    </div>

                    <div className="border-t border-cream-dark pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-ink">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-ink-secondary">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profilo
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Esci
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b border-cream-dark bg-cream">
                    <div className="mx-auto max-w-7xl px-4 py-[0.7rem] sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
