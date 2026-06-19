import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const GoogleIcon = () => (
    <svg viewBox="0 0 24 24" aria-hidden="true" className="h-5 w-5">
        <path fill="#EA4335" d="M12 10.2v4.22h5.88c-.26 1.36-1.03 2.51-2.2 3.28l3.56 2.76c2.07-1.91 3.26-4.72 3.26-8.06 0-.78-.07-1.53-.2-2.25H12z" />
        <path fill="#34A853" d="M12 22c2.97 0 5.45-.98 7.27-2.66l-3.56-2.76c-.98.66-2.25 1.05-3.71 1.05-2.86 0-5.28-1.93-6.15-4.52l-3.68 2.85C3.97 19.56 7.66 22 12 22z" />
        <path fill="#4A90E2" d="M5.85 13.11c-.22-.66-.35-1.36-.35-2.11s.13-1.45.35-2.11l-3.68-2.85C1.42 7.53 1 9.21 1 11s.42 3.47 1.17 4.96l3.68-2.85z" />
        <path fill="#FBBC05" d="M12 4.37c1.62 0 3.07.56 4.21 1.65l3.15-3.15C17.45 1.09 14.97 0 12 0 7.66 0 3.97 2.44 2.17 6.04l3.68 2.85C6.72 6.3 9.14 4.37 12 4.37z" />
    </svg>
);

const MicrosoftIcon = () => (
    <svg viewBox="0 0 24 24" aria-hidden="true" className="h-4 w-4">
        <rect x="2" y="2" width="9" height="9" fill="#F25022" />
        <rect x="13" y="2" width="9" height="9" fill="#7FBA00" />
        <rect x="2" y="13" width="9" height="9" fill="#00A4EF" />
        <rect x="13" y="13" width="9" height="9" fill="#FFB900" />
    </svg>
);

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone_number: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head>
                <title>Join Phronix AI</title>
                <meta name="description" content="Create your Phronix AI account today. Start your journey to academic mastery with intelligent study plans and AI-powered practice exams." />
            </Head>

            <div className="mb-8 text-center">
                <h1 className="text-3xl font-black text-gradient mb-2">Create Account</h1>
                <p className="text-brand-secondary text-sm font-medium">Join the Phronix AI ecosystem today.</p>
            </div>

            {errors.oauth && (
                <div className="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-sm font-medium text-red-600">
                    {errors.oauth}
                </div>
            )}

            <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <a
                    href={route('oauth.redirect', { provider: 'google' })}
                    className="inline-flex w-full items-center justify-center gap-3 rounded-2xl border border-gray-200 bg-white py-3 text-sm font-bold text-brand-text transition-all hover:bg-gray-50 hover:scale-[1.02] active:scale-95 duration-200 shadow-sm"
                >
                    <GoogleIcon />
                    Google
                </a>
                <a
                    href={route('oauth.redirect', { provider: 'microsoft' })}
                    className="inline-flex w-full items-center justify-center gap-3 rounded-2xl border border-gray-200 bg-white py-3 text-sm font-bold text-brand-text transition-all hover:bg-gray-50 hover:scale-[1.02] active:scale-95 duration-200 shadow-sm"
                >
                    <MicrosoftIcon />
                    Microsoft
                </a>
            </div>

            <div className="relative mb-8 flex items-center justify-center">
                <div className="absolute w-full border-t border-gray-100"></div>
                <span className="relative bg-white px-4 text-xs font-bold uppercase tracking-widest text-brand-secondary/40">Or Use Email</span>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="name" value="Full Name" className="text-brand-text ml-1 mb-2 font-bold" />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        placeholder="John Doe"
                        className="mt-1 block w-full rounded-2xl border-gray-200 bg-white text-brand-text placeholder-gray-400 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue/20 transition-all"
                        autoComplete="name"
                        isFocused={true}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="email" value="Email" className="text-brand-text ml-1 mb-2 font-bold" />
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            placeholder="john@example.com"
                            className="mt-1 block w-full rounded-2xl border-gray-200 bg-white text-brand-text placeholder-gray-400 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue/20 transition-all"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="phone_number" value="WhatsApp" className="text-brand-text ml-1 mb-2 font-bold" />
                        <TextInput
                            id="phone_number"
                            type="tel"
                            name="phone_number"
                            value={data.phone_number}
                            placeholder="Optional"
                            className="mt-1 block w-full rounded-2xl border-gray-200 bg-white text-brand-text placeholder-gray-400 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue/20 transition-all"
                            autoComplete="tel"
                            onChange={(e) => setData('phone_number', e.target.value)}
                        />
                        <InputError message={errors.phone_number} className="mt-2" />
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="password" value="Password" className="text-brand-text ml-1 mb-2 font-bold" />
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            placeholder="••••••••"
                            className="mt-1 block w-full rounded-2xl border-gray-200 bg-white text-brand-text placeholder-gray-400 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue/20 transition-all"
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm" className="text-brand-text ml-1 mb-2 font-bold" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            placeholder="••••••••"
                            className="mt-1 block w-full rounded-2xl border-gray-200 bg-white text-brand-text placeholder-gray-400 shadow-sm focus:border-brand-blue focus:ring focus:ring-brand-blue/20 transition-all"
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>
                </div>

                <div className="pt-2">
                    <label className="flex items-start group cursor-pointer">
                        <input
                            type="checkbox"
                            name="terms"
                            checked={data.terms}
                            onChange={(e) => setData('terms', e.target.checked)}
                            className="mt-1 rounded-lg border-gray-300 bg-white text-brand-blue shadow-sm focus:ring-brand-blue/20 transition-all cursor-pointer"
                            required
                        />
                        <span className="ms-3 text-xs font-bold text-brand-secondary/70 group-hover:text-brand-text transition-colors leading-relaxed">
                            I agree to the <Link href={route('terms')} className="text-brand-blue hover:text-blue-600 font-black">Terms</Link> and <Link href={route('privacy')} className="text-brand-blue hover:text-blue-600 font-black">Privacy Policy</Link>
                        </span>
                    </label>
                    <InputError message={errors.terms} className="mt-2" />
                </div>

                <div className="pt-6">
                    <button
                        type="submit"
                        disabled={processing}
                        className="group relative w-full overflow-hidden bg-brand-blue hover:bg-blue-600 text-white font-black py-4 px-6 rounded-2xl shadow-[0_10px_20px_rgba(0,123,255,0.2)] transition-all duration-300 hover:scale-[1.02] active:scale-95 disabled:opacity-50"
                    >
                        <span className="relative z-10">Start Your Journey</span>
                        <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shimmer"></div>
                    </button>

                    <div className="mt-8 text-center">
                        <p className="text-sm font-bold text-brand-secondary">
                            Already a member?{' '}
                            <Link
                                href={route('login')}
                                className="text-brand-blue hover:text-blue-600 transition-colors"
                            >
                                Sign In
                            </Link>
                        </p>
                    </div>
                </div>
            </form>
        </GuestLayout>
    );
}
