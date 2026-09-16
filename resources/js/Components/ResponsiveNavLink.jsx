import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-brand bg-cream text-brand-dark focus:border-brand-dark focus:bg-cream focus:text-brand-dark'
                    : 'border-transparent text-ink-secondary hover:border-brand/30 hover:bg-cream hover:text-ink focus:border-brand/30 focus:bg-cream focus:text-ink'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
