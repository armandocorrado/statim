import { Link } from '@inertiajs/react';

/**
 * Card di sintesi per dashboard direzionali: numero grande, non tabelle di
 * dettaglio. Cliccabile quando `href` è passato (porta alla sezione
 * relativa), altrimenti è un semplice contenitore statico.
 */
export default function StatCard({ title, value, caption, href, children }) {
    const className =
        'block rounded-card bg-white p-6 shadow-soft ring-1 ring-ink/5' +
        (href ? ' transition hover:shadow-lg hover:ring-brand/30' : '');

    const content = (
        <>
            <dt className="text-xs font-medium uppercase tracking-wide text-ink-secondary">
                {title}
            </dt>
            <dd className="mt-2 text-3xl font-semibold text-ink">{value}</dd>
            {caption && (
                <p className="mt-1 text-sm text-ink-secondary">{caption}</p>
            )}
            {children}
        </>
    );

    if (href) {
        return (
            <Link href={href} className={className}>
                {content}
            </Link>
        );
    }

    return <div className={className}>{content}</div>;
}
