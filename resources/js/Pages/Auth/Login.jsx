import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status, canResetPassword, step, studio, studios }) {
    const [showPassword, setShowPassword] = useState(false);
    const { errors } = usePage().props;

    const emailForm = useForm({ email: '' });
    const passwordForm = useForm({ password: '', remember: false });

    const submitEmail = (e) => {
        e.preventDefault();
        emailForm.post(route('login.identify'), { preserveScroll: true });
    };

    const chooseStudio = (tenantId) => {
        router.post(route('login.select-studio'), { tenant_id: tenantId }, { preserveScroll: true });
    };

    const restart = () => {
        router.get(route('login'), { restart: 1 });
    };

    const submitPassword = (e) => {
        e.preventDefault();
        passwordForm.post(route('login'), {
            onFinish: () => passwordForm.reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Accedi" />

            <div className="mb-7">
                <div className="inline-flex items-center gap-2 rounded-full bg-brand/10 px-3 py-1 text-xs font-semibold text-brand mb-3">
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Area Riservata Studio
                </div>
                <h1 className="font-serif text-2xl sm:text-3xl font-bold tracking-tight text-ink">
                    Accedi a MedCare
                </h1>
                <p className="mt-1.5 text-sm text-ink-secondary">
                    {step === 'password' && studio
                        ? <>Stai per accedere a <span className="font-semibold text-ink">{studio.name}</span>.</>
                        : step === 'choose-studio'
                            ? 'La tua email è registrata in più studi: scegli con quale vuoi entrare.'
                            : 'Inserisci le tue credenziali per accedere al gestionale.'}
                </p>
            </div>

            {status && step === 'email' && (
                <div className="mb-5 flex items-center gap-2.5 rounded-xl bg-emerald-50 border border-emerald-200/80 px-4 py-3 text-sm font-medium text-emerald-800">
                    <svg className="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{status}</span>
                </div>
            )}

            {step === 'email' && (
                <form onSubmit={submitEmail} className="space-y-5">
                    <div>
                        <InputLabel htmlFor="email" value="Email professionale" className="font-medium text-ink" />

                        <div className="relative mt-1.5">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-secondary/50">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.75">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </div>

                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={emailForm.data.email}
                                className="block w-full rounded-xl border-ink/15 bg-white/70 pl-10.5 pr-4 py-2.5 text-ink shadow-sm transition placeholder:text-ink-secondary/40 focus:border-brand focus:bg-white focus:ring-4 focus:ring-brand/10"
                                autoComplete="username"
                                placeholder="es. nome@studio.it"
                                isFocused={true}
                                onChange={(e) => emailForm.setData('email', e.target.value)}
                            />
                        </div>

                        <InputError message={emailForm.errors.email} className="mt-2 text-xs" />
                    </div>

                    <PrimaryButton
                        className="w-full justify-center py-3 rounded-xl bg-brand hover:bg-brand-dark active:bg-[#2A4333] text-white font-semibold text-sm shadow-md hover:shadow-lg focus:ring-4 focus:ring-brand/20 transition-all duration-150 flex items-center gap-2 group cursor-pointer"
                        disabled={emailForm.processing}
                    >
                        <span>Continua</span>
                        <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </PrimaryButton>
                </form>
            )}

            {step === 'choose-studio' && (
                <div className="space-y-3">
                    <InputError message={errors?.tenant_id} className="text-xs" />

                    {(studios ?? []).map((s) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => chooseStudio(s.id)}
                            className="flex w-full items-center justify-between rounded-xl border border-ink/15 bg-white/70 px-4 py-3 text-left text-sm font-medium text-ink shadow-sm transition hover:border-brand hover:bg-white hover:shadow-md"
                        >
                            {s.name}
                            <svg className="h-4 w-4 text-ink-secondary/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    ))}

                    <button
                        type="button"
                        onClick={restart}
                        className="mt-2 text-xs font-medium text-ink-secondary hover:text-ink hover:underline"
                    >
                        Usa un'altra email
                    </button>
                </div>
            )}

            {step === 'password' && (
                <form onSubmit={submitPassword} className="space-y-5">
                    <div>
                        <div className="flex items-center justify-between">
                            <InputLabel htmlFor="password" value="Password" className="font-medium text-ink" />
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs font-medium text-brand hover:text-brand-dark transition-colors duration-150 hover:underline"
                                >
                                    Password dimenticata?
                                </Link>
                            )}
                        </div>

                        <div className="relative mt-1.5">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-secondary/50">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.75">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>

                            <TextInput
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                value={passwordForm.data.password}
                                className="block w-full rounded-xl border-ink/15 bg-white/70 pl-10.5 pr-11 py-2.5 text-ink shadow-sm transition placeholder:text-ink-secondary/40 focus:border-brand focus:bg-white focus:ring-4 focus:ring-brand/10"
                                autoComplete="current-password"
                                placeholder="••••••••"
                                isFocused={true}
                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                            />

                            <button
                                type="button"
                                onClick={() => setShowPassword((prev) => !prev)}
                                className="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-secondary/50 hover:text-ink focus:outline-none transition-colors"
                                aria-label={showPassword ? 'Nascondi password' : 'Mostra password'}
                            >
                                {showPassword ? (
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.75">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                    </svg>
                                ) : (
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.75">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                )}
                            </button>
                        </div>

                        <InputError message={passwordForm.errors.password} className="mt-2 text-xs" />
                    </div>

                    <div className="flex items-center">
                        <label className="flex items-center cursor-pointer select-none group">
                            <Checkbox
                                name="remember"
                                checked={passwordForm.data.remember}
                                className="rounded text-brand focus:ring-brand"
                                onChange={(e) => passwordForm.setData('remember', e.target.checked)}
                            />
                            <span className="ms-2.5 text-sm text-ink-secondary group-hover:text-ink transition-colors">
                                Mantieni l'accesso per questa sessione
                            </span>
                        </label>
                    </div>

                    <PrimaryButton
                        className="w-full justify-center py-3 rounded-xl bg-brand hover:bg-brand-dark active:bg-[#2A4333] text-white font-semibold text-sm shadow-md hover:shadow-lg focus:ring-4 focus:ring-brand/20 transition-all duration-150 flex items-center gap-2 group cursor-pointer"
                        disabled={passwordForm.processing}
                    >
                        {passwordForm.processing ? (
                            <>
                                <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span>Accesso in corso...</span>
                            </>
                        ) : (
                            <>
                                <span>Accedi allo Studio</span>
                                <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </>
                        )}
                    </PrimaryButton>

                    <button
                        type="button"
                        onClick={restart}
                        className="w-full text-center text-xs font-medium text-ink-secondary hover:text-ink hover:underline"
                    >
                        Non sei tu? Usa un'altra email
                    </button>
                </form>
            )}

            <div className="pt-6 mt-6 border-t border-ink/5 flex items-center justify-center gap-2 text-xs text-ink-secondary/70">
                <svg className="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span>Connessione protetta &bull; Crittografia SSL a 256 bit</span>
            </div>
        </GuestLayout>
    );
}
