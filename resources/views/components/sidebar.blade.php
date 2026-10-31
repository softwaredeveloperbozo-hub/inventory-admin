<aside class="sidebar">
    <div class="brand">
        <x-brand-mark />
        Painel de Estoque
    </div>

    <nav class="nav">
        <x-nav-link page="dashboard" label="Painel">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
        </x-nav-link>

        <x-nav-link page="products" label="Produtos">
            <path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/>
            <path d="M3 8l9 5 9-5M12 13v8"/>
        </x-nav-link>

        <x-nav-link page="purchases" label="Compras">
            <path d="M12 3v12m0 0l-4-4m4 4l4-4"/>
            <path d="M4 17v3h16v-3"/>
        </x-nav-link>

        <x-nav-link page="sales" label="Vendas">
            <path d="M3 4h2l2.4 11h11l2-8H6.2"/>
            <circle cx="9" cy="20" r="1.5"/>
            <circle cx="18" cy="20" r="1.5"/>
        </x-nav-link>

        <x-nav-link page="categories" label="Categorias">
            <path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L3 13V3h10l7.6 7.6a2 2 0 010 2.8z"/>
            <circle cx="7.5" cy="7.5" r="1.5"/>
        </x-nav-link>

        <x-nav-link page="suppliers" label="Fornecedores">
            <path d="M1 6h13v10H1z"/>
            <path d="M14 10h4l3 3v3h-7z"/>
            <circle cx="6" cy="18" r="2"/>
            <circle cx="17" cy="18" r="2"/>
        </x-nav-link>

        <x-nav-link page="users" label="Usuários" class="admin-only hidden">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </x-nav-link>

        <x-nav-link page="config" label="Configurações">
            <path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/>
            <path d="M1 14h6M9 8h6M17 16h6"/>
        </x-nav-link>
    </nav>

    <div class="sidebar-user">
        <span id="user-name"></span>
        <button id="logout-btn" class="btn small">Sair</button>
    </div>
</aside>
