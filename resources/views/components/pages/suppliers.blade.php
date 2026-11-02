<main id="page-suppliers" class="container hidden">
    <x-page-head title="Fornecedores">
        <button id="new-supplier-btn" class="btn primary">+ Novo fornecedor</button>
    </x-page-head>

    <div class="toolbar">
        <input type="search" id="supplier-search" maxlength="100" autocomplete="off"
               placeholder="Buscar por nome, NIF ou telefone...">
        <select id="supplier-active">
            <option value="">Todos os estados</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
        </select>
    </div>

    <x-data-table
        body="supplier-rows"
        :columns="['Nome', 'NIF', 'Contacto', ['Compras', 'num'], 'Estado', '']" />

    <x-pager info="supplier-pager-info" prev="supplier-prev" next="supplier-next" />
</main>

<x-modal id="supplier-dialog">
    <form id="supplier-form">
        <h2 id="supplier-title">Novo fornecedor</h2>

        <div class="grid">
            <label class="span2">Nome do Fornecedor
                <input id="s-name" required maxlength="150">
            </label>
            <label>NIF
                <input id="s-tax" maxlength="30">
            </label>
            <label>Telefone
                <input id="s-phone" maxlength="30">
            </label>
            <label class="span2">Email
                <input id="s-email" type="email" maxlength="120">
            </label>
            <label class="span2">Morada
                <input id="s-address" maxlength="200">
            </label>
            <label class="check span2">
                <input id="s-active" type="checkbox" checked> Fornecedor ativo
            </label>
        </div>

        <p class="hint">Fornecedores inativos não aparecem em compras novas.</p>
        <ul id="s-errors" class="error hidden"></ul>

        <div class="dialog-actions">
            <button type="button" id="s-cancel" class="btn">Cancelar</button>
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</x-modal>
