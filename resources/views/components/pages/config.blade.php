<main id="page-config" class="container hidden">
    <x-page-head title="Configurações" />

    <div class="settings">
        <form id="cfg-profile-form" class="card settings-card">
            <div>
                <h2>Os meus dados</h2>
                <p class="muted">Nome e email com que entra no sistema.</p>
            </div>

            <label>Nome
                <input id="cfg-name" required maxlength="100" autocomplete="name">
            </label>
            <label>Email
                <input id="cfg-email" type="email" required maxlength="120" autocomplete="username">
            </label>
            <label>Perfil
                <input id="cfg-role" disabled>
            </label>

            <ul id="cfg-profile-errors" class="error hidden"></ul>

            <div class="form-actions">
                <button type="submit" class="btn primary">Guardar dados</button>
            </div>
        </form>

        <form id="cfg-password-form" class="card settings-card">
            <div>
                <h2>Alterar senha</h2>
                <p class="muted">Depois de alterar, a sessão é encerrada e entra com a senha nova.</p>
            </div>

            <label>Senha atual
                <input id="cfg-current" type="password" required autocomplete="current-password">
            </label>
            <label>Senha nova
                <input id="cfg-new" type="password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <label>Repetir a senha nova
                <input id="cfg-confirm" type="password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>

            <p class="hint">Pelo menos 8 caracteres.</p>
            <ul id="cfg-password-errors" class="error hidden"></ul>

            <div class="form-actions">
                <button type="submit" class="btn primary">Alterar senha</button>
            </div>
        </form>

        <section class="card settings-card admin-only hidden">
            <div>
                <h2>Outros usuários</h2>
                <p class="muted">Para editar os dados e o perfil de outra pessoa, ou redefinir a senha dela, use a página de usuários.</p>
            </div>
            <div class="form-actions">
                <a href="#users" class="btn">Abrir usuários</a>
            </div>
        </section>
    </div>
</main>
