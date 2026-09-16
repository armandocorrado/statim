import useCountUp from '@/Hooks/useCountUp';

/**
 * Valore numerico di una StatCard con il "conteggio" da 0 al valore
 * finale — puramente un'animazione d'ingresso, il dato resta quello
 * calcolato dal server (vedi useCountUp per l'implementazione e il
 * rispetto di `prefers-reduced-motion`). `null`/`undefined` non anima
 * nulla: è un valore assente ("nessun preventivo ancora"), non uno zero.
 */
export default function AnimatedNumber({ value, format = Math.round, duration }) {
    const animated = useCountUp(value ?? 0, { duration });

    if (value === null || value === undefined) {
        return '—';
    }

    return format(animated);
}
