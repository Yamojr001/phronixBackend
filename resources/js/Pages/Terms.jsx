import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function Terms() {
    const sections = [
        { id: 'agreement', title: '1. Agreement to Terms', icon: 'fas fa-file-contract', content: 'By accessing or using the Phronix AI platform, you agree to be bound by these Terms and Conditions. If you disagree with any part of these terms, you may not access the service.' },
        { id: 'ip', title: '2. Intellectual Property', icon: 'fas fa-brain', content: 'The platform and its original content, features, and functionality are owned by Phronix AI and are protected by international copyright, trademark, patent, trade secret, and other intellectual property or proprietary rights laws. AI-generated study materials are provided for personal educational use only.' },
        { id: 'accounts', title: '3. User Accounts', icon: 'fas fa-user-shield', content: 'When you create an account with us, you must provide accurate, complete, and current information at all times. Failure to do so constitutes a breach of the Terms, which may result in immediate termination of your account.' },
        { id: 'use', title: '4. Acceptable Use', icon: 'fas fa-check-double', content: 'You agree not to use the platform to generate misleading content, attempt to bypass access controls, or distribute malware. The syllabus files you upload must be materials you have the legal right to access and use for personal study.' },
        { id: 'liability', title: '5. Limitation of Liability', icon: 'fas fa-gavel', content: 'In no event shall Phronix AI, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses.' },
    ];

    const handlePrint = () => window.print();

    return (
        <GuestLayout>
            <Head>
                <title>Terms and Conditions</title>
                <meta name="description" content="Review the Terms and Conditions of Phronix AI. Learn about our academic guidelines, intellectual property rules, and commitment to a fair learning ecosystem." />
            </Head>

            <div className="mb-10 text-center">
                <h1 className="text-4xl font-black text-gradient mb-3">Terms of Service</h1>
                <p className="text-brand-secondary text-sm font-medium">Please review our rules and guidelines.</p>
            </div>

            <div className="flex flex-col lg:flex-row gap-8 relative">
                {/* Desktop Navigation Sidebar */}
                <aside className="hidden lg:block w-64 flex-shrink-0">
                    <div className="sticky top-6 space-y-2">
                        <div className="text-xs font-bold uppercase tracking-widest text-brand-secondary/40 mb-4 px-4">Navigation</div>
                        {sections.map(section => (
                            <a
                                key={section.id}
                                href={`#${section.id}`}
                                className="block px-4 py-3 text-sm font-bold text-brand-secondary hover:text-brand-blue hover:bg-brand-blue/5 rounded-xl transition-all border border-transparent hover:border-brand-blue/10"
                            >
                                {section.title.split('. ')[1]}
                            </a>
                        ))}
                        <button
                            onClick={handlePrint}
                            className="w-full mt-6 flex items-center justify-center gap-2 px-4 py-4 text-sm font-black text-brand-blue bg-brand-blue/5 border border-brand-blue/10 rounded-2xl hover:bg-brand-blue hover:text-white transition-all shadow-sm"
                        >
                            <i className="fas fa-print"></i> Print Agreement
                        </button>
                    </div>
                </aside>

                {/* Main Content Sections */}
                <div className="flex-grow space-y-6">
                    <div className="p-4 bg-brand-blue/5 border border-brand-blue/10 rounded-2xl mb-8 flex items-center gap-4 animate-pulse">
                        <div className="w-10 h-10 rounded-full bg-brand-blue/10 flex items-center justify-center text-brand-blue">
                            <i className="fas fa-info-circle"></i>
                        </div>
                        <p className="text-xs font-bold text-brand-blue leading-relaxed">
                            Last updated: {new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                        </p>
                    </div>

                    {sections.map((section, index) => (
                        <section 
                            key={section.id} 
                            id={section.id} 
                            className="bg-white border border-gray-100 p-8 rounded-3xl shadow-sm transition-all hover:shadow-md hover:border-brand-blue/20 group"
                        >
                            <div className="flex items-center gap-5 mb-5">
                                <div className="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center text-xl text-brand-secondary group-hover:bg-brand-blue group-hover:text-white transition-all duration-300 shadow-inner">
                                    <i className={section.icon}></i>
                                </div>
                                <h2 className="text-xl font-black text-brand-dark group-hover:text-brand-blue transition-colors">
                                    {section.title}
                                </h2>
                            </div>
                            <p className="text-brand-secondary leading-relaxed font-medium">
                                {section.content}
                            </p>
                        </section>
                    ))}

                    <div className="pt-10 flex flex-col sm:flex-row items-center justify-between gap-6 border-t border-gray-100">
                        <Link
                            href={route('login')}
                            className="flex items-center gap-2 text-sm font-black text-brand-secondary hover:text-brand-blue transition-colors group"
                        >
                            <i className="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
                            Return to Secure Login
                        </Link>
                        <div className="flex items-center gap-4">
                            <Link 
                                href={route('privacy')}
                                className="text-xs font-bold text-brand-secondary/60 hover:text-brand-blue transition-colors"
                            >
                                Privacy Policy
                            </Link>
                            <span className="text-gray-200">•</span>
                            <span className="text-xs font-bold text-brand-secondary/40 italic">
                                Phronix Ecosystem v2.0
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}

// Add FontAwesome and Inter/Outfit fonts in GuestLayout if needed, but assuming they are available via CDN or system-wide.
