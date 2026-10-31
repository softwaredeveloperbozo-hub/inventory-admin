@php
    // [ação, administrador, operador]
    $permissions = [
        ['Ver painel, produtos, compras e vendas', true, true],
        ['Criar e editar produtos, categorias e fornecedores', true, true],
        ['Registar vendas (PDV)', true, true],
        ['Criar rascunhos de compra', true, true],
        ['Confirmar compras (define o preço e soma o estoque)', true, false],
        ['Cancelar compras e vendas', true, false],
        ['Excluir produtos, categorias e fornecedores', true, false],
        ['Gerir usuários', true, false],
    ];
@endphp

<main id="page-users" class="container hidden">
    <x-page-head title="Usuários">
        <button id="new-user-btn" class="btn primary">+ Novo usuário</button>
    </x-page-head>

    <div class="toolbar">
        <input type="search" id="user-search" maxlength="100" autocomplete="off"
               placeholder="Buscar por nome ou email...">
        <select id="user-role">
            <option value="">Todos os perfis</option>
            <option value="admin">Administradores</option>
            <option value="operator">Operadores</option>
        </select>
        <select id="user-active">
            <option value="">Todos os estados</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
        </select>
    </div>

    <x-data-table
        body="user-rows"
        :columns="['Nome', 'Email', 'Perfil', 'Estado', 'Criado em', '']" />

    <x-pager info="user-pager-info" prev="user-prev" next="user-next" />

    <h2 class="section-title">O que cada perfil pode fazer</h2>

    <div class="card table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ação</th>
                    <th>Administrador</th>
                    <th>Operador</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as [$action, $admin, $operator])
                    <tr>
                        <td>{{ $action }}</td>
                        <td><span class="badge {{ $admin ? 'ok' : 'off' }}">{{ $admin ? 'Sim' : 'Não' }}</span></td>
                        <td><span class="badge {{ $operator ? 'ok' : 'off' }}">{{ $operator ? 'Sim' : 'Não' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</main>

<x-modal id="user-dialog">
    <form id="user-form" autocomplete="off">
        <h2 id="user-title">Novo usuário</h2>

        <div class="grid">
            <label class="span2">Nome
                <input id="u-name" required maxlength="100">
            </label>
            <label class="span2">Email
                <input id="u-email" type="email" required maxlength="120">
            </label>
            <label>Perfil
                <select id="u-role">
                    <option value="operator">Operador</option>
                    <option value="admin">Administrador</option>
                </select>
            </label>
            <label>Senha
                <input id="u-password" type="password" minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <label class="check span2">
                <input id="u-show" type="checkbox"> Mostrar senha
            </label>
            <label class="check span2">
                <input id="u-active" type="checkbox" checked> Usuário ativo
            </label>
        </div>

        <p id="u-hint" class="hint"></p>
        <ul id="u-errors" class="error hidden"></ul>

        <div class="dialog-actions">
            <button type="button" id="u-cancel" class="btn">Cancelar</button>
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</x-modal>
