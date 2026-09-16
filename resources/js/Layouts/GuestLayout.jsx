import medcareLogo from '../../images/logo-medcare.png';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-surface via-cream to-cream-dark px-4 py-10">
            <div className="w-full sm:max-w-md">
                <div className="overflow-hidden rounded-card border border-ink/10 bg-white p-8 shadow-soft sm:p-10">
                    <div className="mb-6 flex flex-col items-center text-center">
                        <Link href="/" className="mb-3">
                            <img
                                src={medcareLogo}
                                alt="MedCare"
                                className="h-32 w-32 object-contain"
                            />
                        </Link>
                        <span className="font-serif text-xl font-semibold text-ink">
                            MedCare
                        </span>
                        <span className="text-sm text-ink-secondary">
                            Gestionale per professioni sanitarie
                        </span>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
