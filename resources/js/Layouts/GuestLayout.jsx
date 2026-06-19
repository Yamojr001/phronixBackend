import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, Head } from '@inertiajs/react';
import WhatsAppFloatingButton from '@/Components/WhatsAppFloatingButton';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center p-6 bg-brand-light relative overflow-hidden">
            <Head>
                <meta name="description" content="Phronix AI - The world's most advanced AI-powered academic platform. Master your course syllabus with intelligent study plans and practice tests." />
            </Head>

            {/* Hidden H1 for SEO dominance */}
            <h1 className="sr-only">Phronix AI - Your Intelligent Academic Companion & AI Study Room</h1>

            {/* Animated Background Orbs - Subtler for Light Mode */}
            <div className="absolute top-0 left-0 w-full h-full z-0 overflow-hidden pointer-events-none opacity-50">
                <div className="absolute -top-24 -left-24 w-96 h-96 bg-brand-blue/10 rounded-full blur-3xl animate-float"></div>
                <div className="absolute top-1/2 -right-24 w-80 h-80 bg-brand-blue/15 rounded-full blur-3xl animate-float" style={{ animationDelay: '2s' }}></div>
                <div className="absolute -bottom-24 left-1/4 w-64 h-64 bg-indigo-200/20 rounded-full blur-3xl animate-float" style={{ animationDelay: '4s' }}></div>
            </div>

            <div className="relative z-10 flex flex-col items-center w-full max-w-md">
                <div className="mb-10 transform transition-transform hover:scale-105 duration-300">
                    <Link href="/">
                        <ApplicationLogo className="text-6xl gap-4 p-4 drop-shadow-xl" />
                    </Link>
                </div>

                <div className="w-full bg-white/70 backdrop-blur-2xl border border-white p-8 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.05)] animate-in fade-in zoom-in duration-500">
                    {children}
                </div>

                <div className="mt-8 text-brand-secondary/60 text-sm font-medium">
                    &copy; {new Date().getFullYear()} Phronix AI Ecosystem. All rights reserved.
                </div>
            </div>
            
            <WhatsAppFloatingButton />
        </div>
    );
}
