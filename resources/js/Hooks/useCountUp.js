import { useEffect, useRef, useState } from 'react';

function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
}

/**
 * Numero che "conta su" da 0 al valore target al montaggio — solo un
 * colpo d'occhio in più sulle card di sintesi, niente di più: durata
 * breve, easing morbido, e rispetta `prefers-reduced-motion` (mostra
 * subito il valore finale, nessuna animazione forzata a chi l'ha
 * disattivata a livello di sistema).
 */
export default function useCountUp(target, { duration = 900 } = {}) {
    const numericTarget = Number(target) || 0;
    const [value, setValue] = useState(numericTarget);
    const frameRef = useRef();

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            setValue(numericTarget);

            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setValue(numericTarget);

            return;
        }

        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            setValue(numericTarget * easeOutCubic(progress));

            if (progress < 1) {
                frameRef.current = requestAnimationFrame(tick);
            }
        };

        setValue(0);
        frameRef.current = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frameRef.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [numericTarget, duration]);

    return value;
}
