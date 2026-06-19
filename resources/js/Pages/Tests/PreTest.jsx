import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function PreTest({ auth, course, questions }) {
    const { data, setData, post, processing } = useForm({
        answers: Array(questions.length).fill(null),
    });

    const handleAnswerChange = (questionIndex, optionIndex) => {
        const newAnswers = [...data.answers];
        newAnswers[questionIndex] = optionIndex;
        setData('answers', newAnswers);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('courses.test.store', course.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-bold text-xl text-brand-dark leading-tight">Pre-Test - {course.title}</h2>}
        >
            <Head title={`Pre-Test - ${course.title}`} />

            <div className="min-h-screen bg-brand-light font-sans py-12">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                    <div className="text-center mb-8">
                        <span className="bg-brand-blue/10 text-brand-blue font-black px-4 py-1.5 rounded-full text-sm uppercase tracking-wide">Initial Knowledge Check</span>
                        <h1 className="text-3xl font-black text-brand-dark mt-4">Course Pre-Test</h1>
                        <p className="text-brand-secondary mt-2 font-medium">{course.title} ({course.code}) &mdash; {questions.length} Questions</p>
                        <p className="text-brand-secondary text-sm mt-1">This initial assessment helps PrePAI tailor the perfect study plan for you.</p>
                    </div>

                    <div className="bg-brand-white p-6 sm:p-10 rounded-2xl shadow-xl border border-gray-100">
                        <form onSubmit={submit}>
                            {questions.map((q, questionIndex) => (
                                <div key={questionIndex} className="mb-10 pb-10 border-b border-gray-100 last:border-b-0 last:mb-0 last:pb-0">
                                    <div className="flex gap-4 mb-5">
                                        <div className="flex-shrink-0 w-8 h-8 rounded-full bg-brand-blue/10 text-brand-blue flex items-center justify-center font-bold">
                                            {questionIndex + 1}
                                        </div>
                                        <p className="text-lg font-bold text-brand-dark leading-relaxed pt-1">
                                            {q.question}
                                        </p>
                                    </div>
                                    <div className="mt-4 space-y-3 pl-12">
                                        {q.options.map((option, optionIndex) => (
                                            <label key={optionIndex} className="flex items-center p-4 w-full rounded-xl border-2 border-gray-100 cursor-pointer hover:border-brand-blue/30 has-[:checked]:bg-brand-blue/5 has-[:checked]:border-brand-blue transition-all">
                                                <input
                                                    type="radio"
                                                    name={`question_${questionIndex}`}
                                                    className="h-5 w-5 text-brand-blue focus:ring-brand-blue"
                                                    onChange={() => handleAnswerChange(questionIndex, optionIndex)}
                                                    required
                                                />
                                                <span className="ml-4 text-brand-dark font-medium">{option}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}

                            <div className="mt-12 text-center pt-8 border-t border-gray-200">
                                <button type="submit" disabled={processing} className="w-full sm:w-auto px-12 py-4 bg-brand-blue text-white font-black rounded-xl shadow-xl shadow-brand-blue/30 hover:bg-blue-600 hover:-translate-y-1 disabled:opacity-50 transition-all text-lg flex items-center justify-center gap-2 mx-auto">
                                    {processing ? (
                                        <><i className="fas fa-spinner fa-spin"></i> Submitting...</>
                                    ) : (
                                        <><i className="fas fa-check-circle"></i> Submit & See Results</>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}