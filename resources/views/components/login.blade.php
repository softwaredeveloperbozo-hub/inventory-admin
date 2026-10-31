<section id="login-view" class="login hidden">
    <form id="login-form" class="card login-card">
        <x-brand-mark />
        <h1>Painel de Estoque</h1>
        <p class="muted">Entre para registar compras, vendas e acompanhar o estoque.</p>

        <label>Email
            <input type="email" id="login-email" required autocomplete="username">
        </label>
        <label>Senha
            <input type="password" id="login-password" required autocomplete="current-password">
        </label>

        <p id="login-error" class="error hidden"></p>
        <button type="submit" class="btn primary">Entrar</button>
    </form>
</section>
