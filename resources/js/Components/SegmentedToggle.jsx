/**
 * Selettore di periodo/granularità a pillole — controllo standard,
 * non un "mark" del grafico (vedi skill dataviz, interaction.md): stile
 * coerente con lo chrome della card, non con la palette dei dati.
 */
export default function SegmentedToggle({ options, value, onChange }) {
    return (
        <div className="inline-flex rounded-control bg-cream p-0.5 text-sm">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange(option.value)}
                    className={`rounded-[0.5rem] px-3 py-1 font-medium transition ${
                        value === option.value
                            ? 'bg-white text-ink shadow-soft'
                            : 'text-ink-secondary hover:text-ink'
                    }`}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
