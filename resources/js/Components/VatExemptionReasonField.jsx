import { useState } from 'react';

/**
 * Select con le causali di esenzione IVA più comuni (da
 * App\Core\Billing\Support\VatExemptionReasons, passate come prop
 * `reasons` dal controller) + "Altro" che rivela un campo libero. Il
 * valore salvato resta sempre testo semplice — questo componente è solo
 * un aiuto alla digitazione, non una validazione: vedi CLAUDE.md,
 * sezione "Fatturazione".
 */
export default function VatExemptionReasonField({ value, onChange, reasons, className = '' }) {
    const [otherMode, setOtherMode] = useState(
        value !== '' && !Object.prototype.hasOwnProperty.call(reasons, value),
    );

    const selectValue = otherMode ? '__other__' : value;

    return (
        <div className={className}>
            <select
                className="w-full rounded-control border-ink/15 text-sm shadow-sm focus:border-brand focus:ring-brand"
                value={selectValue}
                onChange={(e) => {
                    const next = e.target.value;

                    if (next === '__other__') {
                        setOtherMode(true);

                        return;
                    }

                    setOtherMode(false);
                    onChange(next);
                }}
            >
                <option value="">— nessuna —</option>
                {Object.entries(reasons).map(([reasonValue, label]) => (
                    <option key={reasonValue} value={reasonValue}>
                        {label}
                    </option>
                ))}
                <option value="__other__">Altro (specifica)…</option>
            </select>
            {otherMode && (
                <input
                    type="text"
                    className="mt-1 w-full rounded-control border-ink/15 text-sm shadow-sm focus:border-brand focus:ring-brand"
                    placeholder="Specifica il motivo"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
            )}
        </div>
    );
}
