/**
 * Contenitore per un grafico di sintesi — stessa "pelle" di StatCard
 * (rounded-card, shadow-soft, ring) così i grafici restano coerenti con
 * il resto della dashboard. A differenza di StatCard non è mai
 * cliccabile: un grafico si esplora sul posto (tooltip/periodo), non
 * porta altrove.
 */
export default function ChartCard({ title, subtitle, action, children }) {
    return (
        <div className="rounded-card bg-white p-6 shadow-soft ring-1 ring-ink/5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-serif text-lg font-semibold text-ink">{title}</h3>
                    {subtitle && (
                        <p className="mt-0.5 text-sm text-ink-secondary">{subtitle}</p>
                    )}
                </div>
                {action && <div className="shrink-0">{action}</div>}
            </div>
            <div className="mt-6">{children}</div>
        </div>
    );
}
