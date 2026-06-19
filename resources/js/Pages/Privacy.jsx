import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function Privacy() {
    const sections = [
        { id: 'collection', title: '1. Information We Collect', icon: 'fas fa-user-tag', content: 'The personal information that you are asked to provide, and the reasons why you are asked to provide it, will be made clear to you at the point we ask you to provide your personal information. When you register for an Account, we may ask for your name, email address, and educational preferences.' },
        { id: 'materials', title: '2. Uploaded Materials', icon: 'fas fa-sparkles', content: 'Any course files (PDFs, Images, PPTs) you upload are processed by our system strictly for the purpose of generating your educational reading plans, topics, and tests. We do not sell or distribute these files to third parties. They are used solely to power the AI features of your account.' },
        { id: 'usage', title: '3. How We Use It', icon: 'fas fa-chart-line', content: 'We use the information we collect to operate and maintain our website, improve and personalize your experience, understand how you use our platform, and develop new features like Smart Reminders and AI-driven study paths.' },
        { id: 'security', title: '4. Data Security', icon: 'fas fa-shield-alt', content: 'We value your trust in providing us your Personal Information, thus we are striving to use commercially acceptable means of protecting it. We employ industry-standard encryption and security protocols to ensure your data remains your own.' },
    ];

    const handlePrint = () => window.print();

    return (
        <GuestLayout>
            <Head>
                <title>Privacy Policy</title>
                <meta name="description" content="Your data, protected. Read the Phronix AI Privacy Policy to understand how we secure your academic materials and personal information." />
            </Head>

            <div className="mb-10 text-center">
                <h1 className="text-4xl font-black text-gradient mb-3">Privacy Policy</h1>
                <p className="text-brand-secondary text-sm font-medium">Your data, protected by Phronix AI.</p>
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
                            <i className="fas fa-print"></i> Print Version
                        </button>
                    </div>
                </aside>

                {/* Main Content Sections */}
                <div className="flex-grow space-y-6">
                    <div className="p-4 bg-brand-blue/5 border border-brand-blue/10 rounded-2xl mb-8 flex items-center gap-4 animate-pulse">
                        <div className="w-10 h-10 rounded-full bg-brand-blue/10 flex items-center justify-center text-brand-blue">
                            <i className="fas fa-shield-check"></i>
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
                                href={route('terms')}
                                className="text-xs font-bold text-brand-secondary/60 hover:text-brand-blue transition-colors"
                            >
                                Terms of Service
                            </Link>
                            <span className="text-gray-200">•</span>
                            <span className="text-xs font-bold text-brand-secondary/40 italic">
                                Secure Platform
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
