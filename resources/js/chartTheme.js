/**
 * Token colore per i grafici — specchio JS della palette `chart.*` di
 * `tailwind.config.js` (Recharts consuma hex grezzi in `fill`/`stroke`,
 * non classi CSS). Unica fonte di verità per i colori dei grafici: non
 * assegnare mai un hex "a mano" in un componente.
 *
 * Ordine categorico FISSO — validato con lo script dataviz
 * `validate_palette.js` (contrasto, banda di luminosità/chroma,
 * separazione CVD sotto protanopia/deuteranopia) contro lo sfondo
 * `surface` (#FBF8F3) dell'app. Non riordinare: l'ordine stesso è il
 * meccanismo di sicurezza CVD (vedi skill "dataviz" per il motivo). Un
 * colore segue sempre l'identità della serie, mai la sua posizione in
 * classifica — vedi `colorForCategory()`.
 */
export const CATEGORICAL = [
    '#BA562C', // terracotta
    '#0060A3', // blu
    '#127D4B', // verde
    '#8A2861', // prugna
    '#BD8C28', // ocra — sotto 3:1 di contrasto sullo sfondo: va sempre
    // accompagnato da un'etichetta diretta o dalla legenda, mai dal solo
    // colore (vale per ogni fetta/barra di questo colore).
];

/**
 * Rampa sequenziale (magnitudine, non identità) — un'unica tinta
 * verde della famiglia brand, chiaro → scuro. Usata per il grafico del
 * fatturato (area/linea): non passa (e non deve passare) il validatore
 * categorico, che è pensato per serie distinte, non per una rampa
 * mono-tinta — atteso, non un errore.
 */
export const SEQUENTIAL_GREEN = ['#E3F0E7', '#B9DDC5', '#7CC094', '#3D9A63', '#127D4B'];

/**
 * Colori di stato — riservati, mai riusati per "serie 4": accettato =
 * buono, rifiutato = critico, in attesa = neutro (non è un giudizio
 * negativo, solo "non ancora risposto"). Sempre abbinati a
 * etichetta/legenda, mai al solo colore.
 */
export const STATUS = {
    good: '#127D4B',
    critical: '#BA562C',
    neutral: '#8C8577',
};

export const CHROME = {
    grid: '#EFE9DF',
    axis: '#6B6255',
    tooltipBg: '#FFFFFF',
    tooltipBorder: 'rgba(43, 36, 28, 0.10)',
};

/**
 * Assegna un colore categorico stabile per NOME (mai per posizione in un
 * elenco ordinato per valore) — un filtro o un riordinamento non deve mai
 * "ridipingere" le serie superstiti. `AppointmentTypeProvisioner` seeda 5
 * tipi di default nell'ordine qui sotto; un tipo custom aggiunto da uno
 * studio riceve uno slot deterministico via hash del nome, non
 * l'ennesimo colore ciclico (mai ciclare la palette, vedi skill dataviz).
 */
const DEFAULT_APPOINTMENT_TYPE_ORDER = ['Prima visita', 'Controllo', 'Igiene', 'Urgenza', 'Altro'];

function hashToIndex(value, modulo) {
    let hash = 0;
    for (let i = 0; i < value.length; i++) {
        hash = (hash * 31 + value.charCodeAt(i)) >>> 0;
    }
    return hash % modulo;
}

export function colorForCategory(name) {
    const knownIndex = DEFAULT_APPOINTMENT_TYPE_ORDER.indexOf(name);

    if (knownIndex !== -1) {
        return CATEGORICAL[knownIndex % CATEGORICAL.length];
    }

    return CATEGORICAL[hashToIndex(name, CATEGORICAL.length)];
}
