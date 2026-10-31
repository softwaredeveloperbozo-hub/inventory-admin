/* ---------- página de compras ---------- */

const PURCHASE_STATUS = {
  draft: ['Rascunho', 'low'],
  confirmed: ['Confirmada', 'ok'],
  cancelled: ['Cancelada', 'off'],
};

const pur = {
  page: 1,
  status: '',
  seq: 0,
  found: new Map(),   // resultados da busca de produtos
  items: new Map(),   // product_id -> { product, quantity, cost, sale_price }
};

/* ----- lista ----- */

async function loadPurchases() {
  const seq = ++pur.seq;

  const params = new URLSearchParams({ page: pur.page, per_page: 10 });
  if (pur.status) params.set('status', pur.status);

  try {
    const res = await api(`/purchases?${params}`);
    if (seq !== pur.seq) return;

    if (res.data.length === 0 && pur.page > 1) {
      pur.page = Math.max(res.meta.last_page, 1);
      return loadPurchases();
    }

    renderPurchases(res.data);
    $('purchase-pager-info').textContent = res.meta.total === 0 ? '' : `${res.meta.from}–${res.meta.to} de ${res.meta.total}`;
    $('purchase-prev').disabled = res.meta.current_page <= 1;
    $('purchase-next').disabled = res.meta.current_page >= res.meta.last_page;
  } catch (err) {
    if (seq === pur.seq && err.status !== 401) toast(err.message, true);
  }
}

function renderPurchases(rows) {
  if (rows.length === 0) {
    $('purchase-rows').innerHTML = '<tr><td colspan="7" class="empty">Nenhuma compra encontrada.</td></tr>';
    return;
  }

  $('purchase-rows').innerHTML = rows
    .map((p) => {
      const buttons = [`<button class="btn small" data-action="view" data-id="${p.id}">Ver</button>`];

        if (p.status === 'draft') {
            if (isAdmin()) {
                buttons.push(`<button class="btn small primary" data-action="confirm" data-id="${p.id}">Confirmar</button>`);
            }
            buttons.push(`<button class="btn small danger" data-action="delete" data-id="${p.id}">Excluir</button>`);
        }

      if (p.status === 'confirmed' && isAdmin()) {
        buttons.push(`<button class="btn small danger" data-action="cancel" data-id="${p.id}">Cancelar</button>`);
      }

      return `
        <tr>
          <td>#${p.id}</td>
          <td>${dateTime(p.created_at)}</td>
          <td>${esc(p.supplier?.name ?? '—')}</td>
          <td class="num">${p.items_count ?? 0}</td>
          <td class="num">${money(p.total)}</td>
          <td>${statusBadge(PURCHASE_STATUS, p.status)}</td>
          <td class="actions">${buttons.join(' ')}</td>
        </tr>`;
    })
    .join('');
}

$('purchase-prev').addEventListener('click', () => { pur.page--; loadPurchases(); });
$('purchase-next').addEventListener('click', () => { pur.page++; loadPurchases(); });

$('purchase-status').addEventListener('change', (e) => {
  pur.status = e.target.value;
  pur.page = 1;
  loadPurchases();
});

/* ----- ações da lista ----- */

async function purchaseAction(button, { ask, method, path, done }) {
  if (!confirm(ask)) return;

  button.disabled = true;
  try {
    await api(path, { method });
    toast(done);
    await loadPurchases();
  } catch (err) {
    button.disabled = false;
    if (err.status !== 401) toast(err.message, true);
  }
}

$('purchase-rows').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-action]');
  if (!button) return;

  const id = Number(button.dataset.id);

  switch (button.dataset.action) {
    case 'view':
      viewPurchase(id);
      break;
    case 'confirm':
      purchaseAction(button, {
        ask: `Confirmar a compra #${id}?\nO estoque será somado e os preços de venda passam a valer.`,
        method: 'POST',
        path: `/purchases/${id}/confirm`,
        done: 'Compra confirmada. Estoque e preços atualizados.',
      });
      break;
    case 'cancel':
      purchaseAction(button, {
        ask: `Cancelar a compra #${id}?\nO estoque será estornado e o preço anterior volta a valer.`,
        method: 'POST',
        path: `/purchases/${id}/cancel`,
        done: 'Compra cancelada.',
      });
      break;
    case 'delete':
      purchaseAction(button, {
        ask: `Excluir o rascunho #${id}?`,
        method: 'DELETE',
        path: `/purchases/${id}`,
        done: 'Rascunho excluído.',
      });
      break;
  }
});

/* ----- detalhes ----- */

async function viewPurchase(id) {
  try {
    const { data: p } = await api(`/purchases/${id}`);

    $('pv-title').textContent = `Compra #${p.id}`;
    $('pv-meta').textContent = [
      PURCHASE_STATUS[p.status]?.[0] ?? p.status,
      dateTime(p.created_at),
      p.supplier?.name ?? 'Sem fornecedor',
      `por ${p.user?.name ?? '—'}`,
    ].join(' · ');

    $('pv-items').innerHTML = p.items
      .map((i) => `
        <tr>
          <td>${esc(i.product?.name ?? '—')}<br><span class="muted">${esc(i.product?.sku ?? '')}</span></td>
          <td class="num">${qty(i.quantity)}</td>
          <td class="num">${money(i.cost)}</td>
          <td class="num">${money(i.sale_price)}</td>
          <td class="num">${money(i.subtotal)}</td>
        </tr>`)
      .join('');

    $('pv-total').textContent = money(p.total);
    $('purchase-view').showModal();
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
  }
}

$('pv-close').addEventListener('click', () => $('purchase-view').close());

/* ----- formulário: abrir ----- */

function showPurchaseErrors(messages) {
  const el = $('p-errors');
  el.innerHTML = messages.map((m) => `<li>${esc(m)}</li>`).join('');
  el.classList.toggle('hidden', messages.length === 0);
}

async function openPurchaseForm() {
    pur.items.clear();
    renderPurchaseItems();
    $('p-search').value = '';
    hidePicker();
    showPurchaseErrors([]);
    $('purchase-dialog').showModal();

    // o operador só guarda rascunhos: quem confirma é o administrador
    document.querySelector('#purchase-form [data-confirm="1"]').classList.toggle('hidden', !isAdmin());
    document.querySelector('#purchase-form [data-confirm="0"]').classList.toggle('primary', !isAdmin());

  try {
    const res = await api('/suppliers?is_active=1&per_page=100');
    $('p-supplier').innerHTML =
      '<option value="">Sem fornecedor</option>' +
      res.data.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
  }

  $('p-search').focus();
}

$('new-purchase-btn').addEventListener('click', openPurchaseForm);
$('p-cancel').addEventListener('click', () => $('purchase-dialog').close());

/* ----- formulário: buscar produto ----- */

function hidePicker() {
  pur.found.clear();
  $('p-results').classList.add('hidden');
  $('p-results').innerHTML = '';
}

function showPicker(products) {
  pur.found = new Map(products.map((p) => [p.id, p]));

  $('p-results').innerHTML = products.length
    ? products
        .map((p) => `
          <li>
            <button type="button" data-id="${p.id}">
              <strong>${esc(p.name)}</strong>
              <span class="muted">${esc(p.sku)} · estoque ${qty(p.stock?.quantity ?? 0)}${
                p.price ? ` · venda atual ${money(p.price.sale_price)}` : ' · sem preço'
              }</span>
            </button>
          </li>`)
        .join('')
    : '<li class="empty-li">Nenhum produto encontrado.</li>';

  $('p-results').classList.remove('hidden');
}

const searchForPicker = debounce(async (term) => {
  if (!term) return hidePicker();

  try {
    const products = await fetchProducts(term, 8);
    // o usuário já digitou outra coisa enquanto a resposta vinha: descarta
    if ($('p-search').value.trim() !== term) return;
    showPicker(products);
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
  }
}, 300);

$('p-search').addEventListener('input', (e) => searchForPicker(e.target.value.trim()));

// Enter na busca não pode enviar o formulário
$('p-search').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') e.preventDefault();
});

$('p-results').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-id]');
  if (!button) return;

  const product = pur.found.get(Number(button.dataset.id));
  if (product) addPurchaseItem(product);
});

// clicar fora da busca fecha a lista
$('purchase-dialog').addEventListener('click', (e) => {
  if (!e.target.closest('.picker')) hidePicker();
});

/* ----- formulário: itens ----- */

function addPurchaseItem(product) {
  $('p-search').value = '';
  hidePicker();

  if (pur.items.has(product.id)) {
    // já está na lista: só leva o cursor até a quantidade
    $('p-items').querySelector(`tr[data-id="${product.id}"] [data-field="quantity"]`)?.focus();
    return;
  }

  pur.items.set(product.id, { product, quantity: '', cost: '', sale_price: '' });
  renderPurchaseItems();
  $('p-items').querySelector(`tr[data-id="${product.id}"] [data-field="quantity"]`)?.focus();
}

function renderPurchaseItems() {
  if (pur.items.size === 0) {
    $('p-items').innerHTML = '<tr><td colspan="7" class="empty">Busque um produto acima para adicioná-lo.</td></tr>';
    updatePurchaseTotal();
    return;
  }

  $('p-items').innerHTML = [...pur.items.values()]
    .map(({ product, quantity, cost, sale_price }) => {
      const last = product.price
        ? ` · último: custo ${money(product.price.cost_price)} / venda ${money(product.price.sale_price)}`
        : ' · primeira compra';

      return `
        <tr data-id="${product.id}">
          <td><strong>${esc(product.name)}</strong><br><span class="muted">${esc(product.sku)}${last}</span></td>
          <td class="num"><input type="number" data-field="quantity" min="0.001" step="0.001" required value="${esc(quantity)}"></td>
          <td class="num"><input type="number" data-field="cost" min="0" step="0.01" required value="${esc(cost)}"
              placeholder="${product.price ? esc(product.price.cost_price) : '0.00'}"></td>
          <td class="num"><input type="number" data-field="sale_price" min="0" step="0.01" required value="${esc(sale_price)}"
              placeholder="${product.price ? esc(product.price.sale_price) : '0.00'}"></td>
          <td class="num" data-margin></td>
          <td class="num" data-subtotal></td>
          <td><button type="button" class="btn small danger" data-remove aria-label="Remover">×</button></td>
        </tr>`;
    })
    .join('');

  $('p-items').querySelectorAll('tr[data-id]').forEach(updatePurchaseRow);
  updatePurchaseTotal();
}

const isNumber = (v) => v !== '' && !Number.isNaN(Number(v));

function updatePurchaseRow(tr) {
  const item = pur.items.get(Number(tr.dataset.id));
  const q = Number(item.quantity);
  const c = Number(item.cost);
  const s = Number(item.sale_price);

  tr.querySelector('[data-subtotal]').textContent =
    isNumber(item.quantity) && isNumber(item.cost) ? money(round2(q * c)) : '—';

  const margin = tr.querySelector('[data-margin]');
  const ready = isNumber(item.cost) && isNumber(item.sale_price) && s > 0;

  margin.classList.toggle('loss', ready && s < c);
  if (!ready) margin.textContent = '—';
  else if (s < c) margin.textContent = 'prejuízo';
  else margin.textContent = new Intl.NumberFormat('pt-PT', { maximumFractionDigits: 1 }).format(((s - c) / s) * 100) + '%';
}

function updatePurchaseTotal() {
  let total = 0;
  for (const item of pur.items.values()) {
    if (isNumber(item.quantity) && isNumber(item.cost)) total += round2(Number(item.quantity) * Number(item.cost));
  }
  $('p-total').textContent = money(round2(total));
}

// digitar atualiza o estado e os textos, mas NÃO redesenha a tabela (senão o campo perde o foco)
$('p-items').addEventListener('input', (e) => {
  const input = e.target.closest('input[data-field]');
  if (!input) return;

  const tr = input.closest('tr');
  pur.items.get(Number(tr.dataset.id))[input.dataset.field] = input.value;

  updatePurchaseRow(tr);
  updatePurchaseTotal();
});

$('p-items').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-remove]');
  if (!button) return;

  pur.items.delete(Number(button.closest('tr').dataset.id));
  renderPurchaseItems();
});

/* ----- formulário: enviar ----- */

$('purchase-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  if (pur.items.size === 0) return showPurchaseErrors(['Adicione pelo menos um produto.']);

  const wantsConfirm = e.submitter?.dataset.confirm === '1';

  const items = [...pur.items.values()].map(({ product, quantity, cost, sale_price }) => ({
    product_id: product.id,
    quantity: Number(quantity),
    cost: Number(cost),
    sale_price: Number(sale_price),
  }));

  if (
    items.some((i) => i.sale_price < i.cost) &&
    !confirm('Há produtos com preço de venda abaixo do custo (prejuízo).\nContinuar mesmo assim?')
  ) return;

  const buttons = $('purchase-form').querySelectorAll('button[type="submit"]');
  buttons.forEach((b) => (b.disabled = true));
  showPurchaseErrors([]);

  try {
    const created = await api('/purchases', {
      method: 'POST',
      body: {
        supplier_id: $('p-supplier').value ? Number($('p-supplier').value) : null,
        items,
      },
    });

    const id = created.data.id;
    $('purchase-dialog').close();

    if (!wantsConfirm) {
      toast(`Rascunho #${id} guardado.`);
    } else {
      try {
        await api(`/purchases/${id}/confirm`, { method: 'POST' });
        toast(`Compra #${id} confirmada. Estoque e preços atualizados.`);
      } catch (err) {
        // o rascunho já existe: nada se perdeu
        toast(
            isAdmin()
            ? `Rascunho #${id} guardado.`
            : `Rascunho #${id} guardado. Um administrador precisa de o confirmar.`
        );
      }
    }

    await loadPurchases();
  } catch (err) {
    if (err.status !== 401) showPurchaseErrors(errorMessages(err));
  } finally {
    buttons.forEach((b) => (b.disabled = false));
  }
});

PAGE_LOADERS.purchases = loadPurchases;
