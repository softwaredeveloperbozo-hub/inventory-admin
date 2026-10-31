<main id="page-purchases" class="container hidden">
    <x-page-head title="Compras">
        <button id="new-purchase-btn" class="btn primary">+ Nova compra</button>
    </x-page-head>

    <div class="toolbar">
        <select id="purchase-status">
            <option value="">Todos os estados</option>
            <option value="draft">Rascunho</option>
            <option value="confirmed">Confirmada</option>
            <option value="cancelled">Cancelada</option>
        </select>
    </div>

    <x-data-table
        body="purchase-rows"
        :columns="['Nº', 'Data', 'Fornecedor', ['Itens', 'num'], ['Total', 'num'], 'Estado', '']" />

    <x-pager info="purchase-pager-info" prev="purchase-prev" next="purchase-next" />
</main>

<x-modal id="purchase-dialog" wide>
    <form id="purchase-form">
        <h2>Nova compra</h2>

        <label>Fornecedor
            <select id="p-supplier">
                <option value="">Sem fornecedor</option>
            </select>
        </label>

        <div class="picker">
            <input type="search" id="p-search" maxlength="100" autocomplete="off"
                   placeholder="Buscar produto para adicionar...">
            <ul id="p-results" class="picker-list hidden"></ul>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="num">Quantidade</th>
                        <th class="num">Custo (un.)</th>
                        <th class="num">Preço de venda</th>
                        <th class="num">Margem</th>
                        <th class="num">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="p-items"></tbody>
            </table>
        </div>

        <p class="hint">O preço de venda definido aqui passa a valer quando a compra for confirmada.</p>
        <div class="total-line">Total: <strong id="p-total">0,00 Kz</strong></div>
        <ul id="p-errors" class="error hidden"></ul>

        <div class="dialog-actions">
            <button type="button" id="p-cancel" class="btn">Cancelar</button>
            <button type="submit" class="btn" data-confirm="0">Guardar rascunho</button>
            <button type="submit" class="btn primary" data-confirm="1">Guardar e confirmar</button>
        </div>
    </form>
</x-modal>

<x-modal id="purchase-view" wide>
    <div class="view-box">
        <h2 id="pv-title"></h2>
        <p id="pv-meta" class="muted"></p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="num">Quantidade</th>
                        <th class="num">Custo</th>
                        <th class="num">Preço de venda</th>
                        <th class="num">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="pv-items"></tbody>
            </table>
        </div>

        <div class="total-line">Total: <strong id="pv-total"></strong></div>
        <div class="dialog-actions">
            <button type="button" id="pv-close" class="btn">Fechar</button>
        </div>
    </div>
</x-modal>
