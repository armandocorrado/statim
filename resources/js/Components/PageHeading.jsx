/**
 * Titolo di pagina — sempre il serif del brand (Fraunces), dritto e mai
 * corsivo. Unico punto da cui ogni pagina attinge per il proprio header,
 * così l'identità tipografica resta coerente in un solo posto.
 */
export default function PageHeading({ children }) {
    return (
        <h2 className="font-serif text-2xl font-semibold text-ink">
            {children}
        </h2>
    );
}
