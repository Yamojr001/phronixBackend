<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Phronix AI') }}</title>

        <!-- Primary Meta Tags -->
        <meta name="title" content="Phronix AI - Your Intelligent Academic Companion">
        <meta name="description" content="Phronix AI is the world's most advanced AI-powered academic platform. Effortlessly generate study plans, practice tests, and summaries from your course syllabus. Transform your learning experience today.">
        <meta name="keywords" content="AI Tutor, Academic Platform, Study Plans, Practice Tests, Syllabus Extraction, Phronix AI, Intelligent Learning, Student Productivity, Exam Preparation">
        <meta name="author" content="Phronix AI Team">
        <meta name="robots" content="index, follow">
        <meta name="theme-color" content="#007bff">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="Phronix AI - Personalized Academic Excellence">
        <meta property="og:description" content="Generate custom study plans and practice tests in seconds. Join thousands of students using Phronix AI to master their courses.">
        <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="Phronix AI - Your Intelligent Academic Companion">
        <meta property="twitter:description" content="The world's most advanced AI-powered academic platform. Transform your course materials into success.">
        <meta property="twitter:image" content="{{ asset('images/twitter-card.jpg') }}">

        <!-- Structured Data (JSON-LD) -->
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebSite",
            "name": "Phronix AI",
            "url": "{{ url('/') }}",
            "potentialAction": {
                "@@type": "SearchAction",
                "target": "{{ url('/') }}/search?q={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        }
        </script>
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "Organization",
            "name": "Phronix AI",
            "url": "{{ url('/') }}",
            "logo": "{{ asset('images/logo.png') }}",
            "sameAs": [
                "https://twitter.com/phronixai",
                "https://facebook.com/phronixai",
                "https://linkedin.com/company/phronixai"
            ]
        }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        
        <!-- Font Awesome Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>