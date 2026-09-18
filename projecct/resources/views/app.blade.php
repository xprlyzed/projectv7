<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark" data-image-fallback="{{ asset('assets/media/placeholder.svg') }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name') }}</title>
    @php use Artesaos\SEOTools\Facades\SEOMeta; use Artesaos\SEOTools\Facades\OpenGraph; use Artesaos\SEOTools\Facades\TwitterCard; @endphp
    <meta name="description" content="{{ SEOMeta::getDescription() }}" />
    @if(SEOMeta::getCanonical())<link rel="canonical" href="{{ SEOMeta::getCanonical() }}" />@endif
    {!! OpenGraph::generate() !!}
    {!! TwitterCard::generate() !!}
    <link rel="icon" href="{{ asset('assets/media/logos/favicon.svg') }}" type="image/x-icon" />
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('assets/plugins/global/fonts/bootstrap-icons/bootstrap-icons.woff2') }}" crossorigin />
    <link rel="preload" as="font" type="font/woff" href="{{ asset('assets/plugins/global/fonts/keenicons-duotone/keenicons-duotone.woff') }}" crossorigin />
    <link rel="preload" as="font" type="font/woff" href="{{ asset('assets/plugins/global/fonts/keenicons-outline/keenicons-outline.woff') }}" crossorigin />

    <script>
        (function () {
            var t = localStorage.getItem('theme') || 'dark';
            var d = document.documentElement;
            d.classList.remove('light-mode', 'dark-mode');
            d.classList.add(t + '-mode');
            d.setAttribute('data-bs-theme', t === 'dark' ? 'dark' : 'light');
            d.classList.add('loaded');
        })();
    </script>

    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/style.bundle.css') }}?v={{ filemtime(public_path('assets/css/style.bundle.css')) }}" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    @routes
    @vite(['resources/css/legacy.css', 'resources/js/app.js'])
    @inertiaHead
</head>

<body id="kt_app_body" data-kt-app-layout="dark-sidebar" data-kt-app-header-fixed="true" data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true" data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true" data-kt-app-sidebar-push-footer="true">

    <div id="app-loader" aria-hidden="true" role="status">
        <div class="app-loader-inner">
            <div class="app-loader-spin"></div>
        </div>
    </div>

    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="{{ asset('assets/js/custom/theme/image-fallback.js') }}"></script>
    <script src="{{ asset('assets/js/custom/app-toast.js') }}"></script>
    <script src="{{ asset('assets/js/custom/theme/ajax-delete.js') }}"></script>

    @inertia
</body>

</html>
