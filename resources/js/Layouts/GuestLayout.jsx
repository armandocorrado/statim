import medcareLogo from '../../images/logo-medcare.png';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-slate-50 via-blue-50/60 to-slate-100 px-4 py-10">
            <div className="w-full sm:max-w-md">
                <div className="overflow-hidden rounded-2xl border border-slate-200/70 bg-white p-8 shadow-xl shadow-slate-200/60 sm:p-10">
                    <div className="mb-6 flex flex-col items-center text-center">
                        <Link href="/" className="mb-3">
                            <img
                                src={medcareLogo}
                                alt="MedCare"
                                className="h-32 w-32 object-contain"
                            />
                        </Link>
                        <span className="text-lg font-semibold tracking-tight text-slate-900">
                            MedCare
                        </span>
                        <span className="text-sm text-slate-500">
                            Gestionale per professioni sanitarie
                        </span>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
