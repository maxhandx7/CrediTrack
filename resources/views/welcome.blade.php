<!DOCTYPE html>
<html lang="es" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Te mantiene al dia con tus prestamos">
    <meta name="keywords" content="gestion de prestamos, pagos, recordatorios, analitica">
    <meta name="author" content="Alan Carabali">

    <title>Sistema de Gestión de Prestamos | Inicio</title>

    <!-- Preload critical resources -->
    <link rel="preload" as="style" href="{{ Vite::asset('resources/css/app.css') }}">
    <link rel="preload" as="script" href="{{ Vite::asset('resources/js/main.jsx') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('/images/default.svg') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/images/default.svg') }}">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Sistema de Gestión de  Prestamos">
    <meta property="og:description" content="Aplicación telemática para gestión de estudiantes y notas">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <!-- Theme Color -->
    <meta name="theme-color" content="#3b82f6">

    <!-- DNS Prefetch -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Sistema de Gestión Académica",
        "description": "Aplicación telemática para gestión de estudiantes y notas",
        "applicationCategory": "EducationalApplication",
        "operatingSystem": "Web Browser",
        "author": {
            "@type": "Person",
            "name": "Alan Carabali"
        }
    }
    </script>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/main.jsx'])

    <style>
        /* Critical CSS for initial loading */
        loading-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Imagen de fondo desenfocada */
        .loading-container::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('{{ asset('/images/default.svg') }}') center/cover no-repeat;
            filter: blur(8px) brightness(0.6);
            z-index: 0;
        }

        /* Capa translúcida encima para el degradado */
        .loading-container::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.6), rgba(118, 75, 162, 0.7));
            z-index: 1;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #ffffff33;
            border-top: 4px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 2;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Fallback styles if JS fails */
        .no-js-message {
            display: none;
            text-align: center;
            color: white;
            padding: 2rem;
        }

        .no-js .no-js-message {
            display: block;
        }

        .no-js #app {
            display: none;
        }
    </style>
</head>

<body>
    <!-- Loading state -->
    <div id="loading" class="loading-container">
        <div class="text-center">
            <div class="loading-spinner mb-4"></div>
        </div>
    </div>



    <!-- No JavaScript message -->
    <noscript>
        <div class="no-js-message">
            <h1 class="text-2xl font-bold mb-4">JavaScript Requerido</h1>
            <p class="mb-4">Esta aplicación requiere JavaScript para funcionar correctamente.</p>
            <p>Por favor, habilita JavaScript en tu navegador y recarga la página.</p>
        </div>
    </noscript>

    <!-- Main app container -->
    <div id="app"></div>

    <!-- Error boundary fallback -->
    <div id="error-boundary" style="display: none;"
        class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="max-w-md w-full text-center">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-red-500 text-6xl mb-4">⚠️</div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Algo salió mal</h2>
                <p class="text-gray-600 mb-4">Ha ocurrido un error inesperado en la aplicación.</p>
                <button onclick="window.location.reload()"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-6 rounded-lg transition duration-200">
                    Recargar Página
                </button>
            </div>
        </div>
    </div>

    <script>
        // Enhanced loading management
        document.addEventListener('DOMContentLoaded', function() {
            // Remove no-js class if JavaScript is enabled
            document.documentElement.classList.remove('no-js');

            // Hide loading spinner when app is mounted
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        const app = document.getElementById('app');
                        if (app && app.children.length > 0) {
                            const loading = document.getElementById('loading');
                            if (loading) {
                                loading.style.display = 'none';
                            }
                            observer.disconnect();
                        }
                    }
                });
            });

            observer.observe(document.getElementById('app'), {
                childList: true,
                subtree: true
            });

            // Global error handler
            window.addEventListener('error', function(e) {
                console.error('Global error:', e.error);
                const app = document.getElementById('app');
                const errorBoundary = document.getElementById('error-boundary');
                const loading = document.getElementById('loading');

                if (app) app.style.display = 'none';
                if (loading) loading.style.display = 'none';
                if (errorBoundary) errorBoundary.style.display = 'block';
            });

            // Handle Vite connection issues
            window.addEventListener('vite:error', function(e) {
                console.error('Vite error:', e.detail);
            });
        });

        // Service Worker registration (optional)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('SW registered: ', registration);
                    })
                    .catch(function(registrationError) {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
    </script>
</body>

</html>
