<header class="navbar">
    <div class="navbar-title">
        <span id="navbar-date"></span>
        <strong id="navbar-greeting">Olá</strong>
    </div>

    <div class="navbar-actions">
        <div class="notif">
            <button type="button" id="notif-btn" class="icon-btn"
                    aria-label="Notificações" aria-haspopup="true" aria-expanded="false" aria-controls="notif-panel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.7 21a2 2 0 01-3.4 0"/>
                </svg>
                <span id="notif-count" class="notif-count hidden">0</span>
            </button>

            <div id="notif-panel" class="notif-panel hidden">
                <div class="notif-head">
                    <strong>Notificações</strong>
                    <button type="button" id="notif-seen" class="link-btn" disabled>Marcar como vistas</button>
                </div>
                <ul id="notif-list" class="notif-list"></ul>
            </div>
        </div>

        <a href="#config" id="navbar-user" class="avatar" title="A minha conta" aria-label="A minha conta"></a>
    </div>
</header>
