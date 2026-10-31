<main id="page-sales" class="container hidden">
    <x-page-head title="Vendas" />

    <div class="pos">
        <section class="pos-products">
            <input type="search" id="pos-search" maxlength="100" autocomplete="off"
                   placeholder="Buscar produto, SKU ou ler código de barras (Enter)...">
            <div id="pos-grid" class="pos-grid"></div>
        </section>

        <aside class="card pos-cart">
            <h3>Carrinho</h3>
            <div id="cart-items" class="cart-items"></div>

            <label>Cliente (opcional)
                <input id="pos-customer" maxlength="150">
            </label>

            <div class="two">
                <label>Pagamento
                    <select id="pos-payment">
                        <option value="cash">Dinheiro</option>
                        <option value="card">Cartão</option>
                        <option value="pix">Pix</option>
                        <option value="transfer">Transferência</option>
                    </select>
                </label>
                <label>Desconto (Kz)
                    <input id="pos-discount" type="number" min="0" step="0.01" value="0">
                </label>
            </div>

            <div class="cart-totals">
                <div><span>Subtotal</span><span id="cart-gross">0,00 Kz</span></div>
                <div><span>Desconto</span><span id="cart-discount">0,00 Kz</span></div>
                <div class="grand"><span>Total</span><strong id="cart-total">0,00 Kz</strong></div>
            </div>

            <p id="pos-error" class="error hidden"></p>

            <button id="pos-finish" class="btn primary big" disabled>Finalizar venda</button>
            <button id="pos-clear" class="btn" disabled>Limpar carrinho</button>
        </aside>
    </div>

    <h2 class="section-title">Últimas vendas</h2>

    <x-data-table
        body="sale-rows"
        :columns="['Código', 'Data', 'Cliente', 'Pagamento', ['Total', 'num'], 'Estado', '']" />
</main>
