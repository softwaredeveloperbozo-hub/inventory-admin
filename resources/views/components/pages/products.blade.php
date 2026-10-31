<main id="page-products" class="container hidden">
    <x-page-head title="Produtos">
        <button id="export-xlsx" class="btn">Excel</button>
        <button id="export-pdf" class="btn">PDF</button>
        <button id="new-btn" class="btn primary">+ Novo produto</button>
    </x-page-head>

    <div class="toolbar">
        <input type="search" id="search" maxlength="100" autocomplete="off"
               placeholder="Buscar por nome, SKU ou código de barras...">
        <select id="filter-category">
            <option value="">Todas as categorias</option>
        </select>
    </div>

    <x-data-table
        body="rows"
        :columns="['SKU', 'Produto', 'Categoria', ['Estoque', 'num'], ['Preço', 'num'], 'Situação', '']" />

    <x-pager info="pager-info" prev="prev-btn" next="next-btn" />
</main>

<x-modal id="product-dialog">
    <form id="product-form">
        <h2 id="dialog-title">Novo produto</h2>

        <div class="grid">
            <label>SKU
                <input id="f-sku" required maxlength="80">
            </label>
            <label>Código de barras
                <input id="f-barcode" maxlength="120">
            </label>
            <label class="span2">Nome
                <input id="f-name" required maxlength="150">
            </label>
            <label>Categoria
                <select id="f-category">
                    <option value="">Sem categoria</option>
                </select>
            </label>
            <label>Imposto (%)
                <input id="f-tax" type="number" min="0" max="100" step="0.01" value="0">
            </label>
            <label class="span2">Descrição
                <textarea id="f-description" rows="3"></textarea>
            </label>
            <label class="check span2">
                <input id="f-active" type="checkbox" checked> Produto ativo
            </label>
        </div>

        <p class="hint">Preço e quantidade não se editam aqui: nascem das compras.</p>
        <ul id="form-errors" class="error hidden"></ul>

        <div class="dialog-actions">
            <button type="button" id="cancel-btn" class="btn">Cancelar</button>
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</x-modal>
