import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { useEffect, useRef, useState } from 'react';

/**
 * Ricerca-paziente riusata da Agenda (creazione appuntamento) e
 * Fatturazione (paziente/intestatario) — stesso endpoint minimale
 * `agenda.patients-search`, non specifico dell'Agenda nonostante il nome
 * della rotta.
 */
export default function PatientPicker({
    id = 'patient_search',
    label = 'Paziente',
    value,
    initialLabel,
    onSelect,
    error,
}) {
    const [query, setQuery] = useState(initialLabel ?? '');
    const [results, setResults] = useState([]);
    const timeoutRef = useRef(null);

    useEffect(() => {
        setQuery(initialLabel ?? '');
    }, [initialLabel]);

    const search = (text) => {
        setQuery(text);
        onSelect(null, '');

        if (timeoutRef.current) clearTimeout(timeoutRef.current);
        if (text.trim().length < 2) {
            setResults([]);
            return;
        }

        timeoutRef.current = setTimeout(async () => {
            const response = await fetch(
                route('agenda.patients-search', { q: text }),
            );
            setResults(await response.json());
        }, 250);
    };

    return (
        <div className="relative">
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                className="mt-1 block w-full"
                value={query}
                placeholder="Cerca per nome o cognome…"
                onChange={(e) => search(e.target.value)}
                autoComplete="off"
            />
            <InputError message={error} className="mt-2" />
            {results.length > 0 && (
                <ul className="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                    {results.map((patient) => (
                        <li key={patient.id}>
                            <button
                                type="button"
                                className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                                onClick={() => {
                                    onSelect(
                                        patient.id,
                                        `${patient.first_name} ${patient.last_name}`,
                                    );
                                    setResults([]);
                                }}
                            >
                                {patient.first_name} {patient.last_name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
