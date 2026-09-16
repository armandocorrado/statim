import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Sans: testo e dati (tabelle, campi, numeri) — leggibilità
                // massima. Serif: titoli e nome prodotto — identità "wellness
                // clinico", dritto e mai corsivo (vedi PageHeading).
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                serif: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                // Palette corporativa MedCare — unica fonte di verità per i colori
                // di brand. Cambiare qui si propaga a tutta l'app. Verde salvia
                // (H138 S18%) tenuto deliberatamente desaturato per restare
                // distinto dal verde di stato (H142 S76%, es. green-600 di
                // Tailwind) — stessa famiglia di tonalità, saturazione molto
                // diversa: non si confondono a colpo d'occhio.
                brand: {
                    DEFAULT: '#4A6B54',
                    dark: '#3E5C48',
                    // Solo per tint di sfondo/bordo (badge, accenti): contrasto
                    // 3.89:1 con testo bianco, insufficiente per testo piccolo —
                    // mai usarlo come sfondo sotto testo corrente.
                    light: '#6B8873',
                },
                cream: {
                    DEFAULT: '#F3EEE7',
                    dark: '#EFE9DF',
                },
                surface: '#FBF8F3',
                ink: {
                    DEFAULT: '#2B241C',
                    secondary: '#6B6255',
                },
                // Palette dei grafici — NON gli stessi hex di `brand`/`cream`
                // di proposito: quelli sono deliberatamente desaturati per
                // l'identità UI (vedi commento su `brand` sopra), ma un
                // colore categorico deve superare una soglia minima di
                // chroma per restare distinguibile (validato con lo script
                // dataviz `validate_palette.js` — CVD-safe, ordine fisso,
                // mai ciclico). Stessa famiglia cromatica "wellness caldo",
                // calibrata per fare lavoro di identità invece che di brand.
                // Specchio JS in `resources/js/chartTheme.js` (Recharts
                // richiede hex grezzi, non classi Tailwind).
                chart: {
                    terracotta: '#BA562C',
                    blue: '#0060A3',
                    green: '#127D4B',
                    plum: '#8A2861',
                    ochre: '#BD8C28',
                    neutral: '#8C8577',
                },
            },
            borderRadius: {
                control: '0.625rem', // bottoni, input, badge
                card: '1rem', // card, pannelli
            },
            boxShadow: {
                // Ombra diffusa e tinta calda (non nero puro) per le card —
                // "leggera", non un'ombra grigia dura.
                soft: '0 2px 8px -2px rgba(43, 36, 28, 0.08), 0 8px 24px -8px rgba(43, 36, 28, 0.06)',
            },
        },
    },

    plugins: [forms],
};
