<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d2733">
    <title>Painel de Estoque</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

    <x-login />

    <section id="app-view" class="layout hidden">
        <x-sidebar />

        <div class="content">
            <x-navbar />

            <x-pages.dashboard />
            <x-pages.products />
            <x-pages.purchases />
            <x-pages.sales />
            <x-pages.categories />
            <x-pages.suppliers />
            <x-pages.users />
            <x-pages.config />
        </div>
    </section>

    <div id="toast" class="toast hidden"></div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/navbar.js') }}"></script>
    <script src="{{ asset('js/purchases.js') }}"></script>
    <script src="{{ asset('js/sales.js') }}"></script>
    <script src="{{ asset('js/categories.js') }}"></script>
    <script src="{{ asset('js/suppliers.js') }}"></script>
    <script src="{{ asset('js/users.js') }}"></script>
    <script src="{{ asset('js/config.js') }}"></script>
</body>
</html>
