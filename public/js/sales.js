/* ---------- página de vendas (PDV) ---------- */

const SALE_STATUS = {
  completed: ['Concluída', 'ok'],
  cancelled: ['Cancelada', 'off'],
};

const PAYMENT = { cash: 'Dinheiro', card: 'Cartão', pix: 'Pix', transfer: 'Transferência' };

const pos = {
  cart: new Map(),      // product_id -> { product, quantity }
  products: new Map(),  // o que está na grade agora
  seq: 0,
  busy: false,
};

/* ----- grade de produtos ----- */

async function posLoad(term = '') {
  const seq = ++pos.seq;

  try {
    const products = await fetchProducts(term, 12);
    if (seq !== pos.seq) return null; // resposta antiga: descarta

    pos.products = new Map(products.map((p) => [p.id, p]));
    renderGrid(products);
    return products;
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
    return null;
  }
}

function renderGrid(products) {
  if (products.length === 0) {
    $('pos-grid').innerHTML = '<p class="muted">Nenhum produto encontrado.</p>';
    return;
  }

  $('pos-grid').innerHTML = products
    .map((p) => {
      const stock = p.stock?.quantity ?? 0;

      let blocked = '';
      if (!p.is_active) blocked = 'inativo';
      else if (!p.price) blocked = 'sem preço';
      else if (stock <= 0) blocked = 'sem estoque';

      return `
        <button type="button" class="pos-card" data-id="${p.id}" ${blocked ? 'disabled' : ''}>
          <strong>${esc(p.name)}</strong>
          <span class="muted">${esc(p.sku)}</span>
          <span class="pos-price">${p.price ? money(p.price.sale_price) : '—'}</span>
          <span class="${blocked ? 'loss' : 'muted'}">${blocked ? esc(blocked) : `estoque ${qty(stock)}`}</span>
        </button>`;
    })
    .join('');
}

const posSearch = debounce((term) => posLoad(term), 300);

$('pos-search').addEventListener('input', (e) => posSearch(e.target.value.trim()));

// Enter = leitor de código de barras: se achar exatamente um produto, já põe no carrinho
$('pos-search').addEventListener('keydown', async (e) => {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  posSearch.cancel();

  const term = e.target.value.trim();
  if (!term) return;

  const found = await posLoad(term);
  if (!found) return;

  const lower = term.toLowerCase();
  const pick =
    found.find((p) => p.barcode === term || p.sku.toLowerCase() === lower) ??
    (found.length === 1 ? found[0] : null);

  if (pick) {
    addToCart(pick);
    e.target.value = '';
    await posLoad('');
  }
});

$('pos-grid').addEventListener('click', (e) => {
  const card = e.target.closest('button[data-id]');
  if (!card) return;

  const product = pos.products.get(Number(card.dataset.id));
  if (product) addToCart(product);
});

/* ----- carrinho ----- */

function addToCart(product) {
  const stock = Number(product.stock?.quantity ?? 0);

  if (!product.is_active || !product.price) {
    toast(`"${product.name}" não pode ser vendido (inativo ou sem preço).`, true);
    return;
  }

  const line = pos.cart.get(product.id);
  const next = line ? round3(line.quantity + 1) : 1;

  if (next > stock) {
    toast(`Estoque máximo de "${product.name}" atingido (${qty(stock)}).`, true);
    return;
  }

  if (line) line.quantity = next;
  else pos.cart.set(product.id, { product, quantity: 1 });

  renderCart();
}

function renderCart() {
  if (pos.cart.size === 0) {
    $('cart-items').innerHTML = '<p class="muted">Carrinho vazio. Clique num produto para adicionar.</p>';
  } else {
    $('cart-items').innerHTML = [...pos.cart.values()]
      .map(({ product, quantity }) => `
        <div class="cart-row" data-id="${product.id}">
          <div class="cart-name">
            <strong>${esc(product.name)}</strong>
            <span class="muted">${money(product.price.sale_price)} · estoque ${qty(product.stock?.quantity ?? 0)}</span>
          </div>
          <input type="number" class="cart-qty" min="0.001" step="any" value="${quantity}" aria-label="Quantidade">
          <span class="cart-sub" data-sub></span>
          <button type="button" class="btn small danger" data-remove aria-label="Remover">×</button>
        </div>`)
      .join('');
  }

  updateCart();
}

// recalcula subtotais, total e avisos SEM redesenhar as linhas (para o campo não perder o foco)
function updateCart() {
  let gross = 0;
  const issues = [];

  for (const line of pos.cart.values()) {
    const stock = Number(line.product.stock?.quantity ?? 0);
    const price = Number(line.product.price.sale_price);
    const valid = line.quantity > 0;
    const over = valid && line.quantity > stock;
    const sub = valid ? round2(line.quantity * price) : 0;
    gross += sub;

    const row = $('cart-items').querySelector(`.cart-row[data-id="${line.product.id}"]`);
    row.querySelector('[data-sub]').textContent = money(sub);
    row.querySelector('input').classList.toggle('bad', !valid || over);

    if (!valid) issues.push(`Informe a quantidade de "${line.product.name}".`);
    else if (over) issues.push(`Estoque insuficiente de "${line.product.name}" (disponível: ${qty(stock)}).`);
  }

  const discount = Math.max(Number($('pos-discount').value) || 0, 0);
  if (discount > gross) issues.push('O desconto não pode ser maior que o total da venda.');

  $('cart-gross').textContent = money(gross);
  $('cart-discount').textContent = money(discount);
  $('cart-total').textContent = money(Math.max(round2(gross - discount), 0));

  showPosError(issues[0] ?? '');
  $('pos-finish').disabled = pos.cart.size === 0 || issues.length > 0 || pos.busy;
  $('pos-clear').disabled = pos.cart.size === 0 || pos.busy;
}

function showPosError(message) {
  $('pos-error').textContent = message;
  $('pos-error').classList.toggle('hidden', !message);
}

$('cart-items').addEventListener('input', (e) => {
  const input = e.target.closest('input');
  if (!input) return;

  const id = Number(input.closest('.cart-row').dataset.id);
  pos.cart.get(id).quantity = Number(input.value); // vazio vira 0 e é tratado como inválido
  updateCart();
});

$('cart-items').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-remove]');
  if (!button) return;

  pos.cart.delete(Number(button.closest('.cart-row').dataset.id));
  renderCart();
});

$('pos-discount').addEventListener('input', updateCart);

$('pos-clear').addEventListener('click', () => {
  pos.cart.clear();
  renderCart();
});

/* ----- finalizar ----- */

$('pos-finish').addEventListener('click', async () => {
  pos.busy = true; // trava duplo clique: sem isso, duas vendas seriam criadas
  updateCart();

  const body = {
    payment_method: $('pos-payment').value,
    customer_name: $('pos-customer').value.trim() || null,
    discount: round2(Number($('pos-discount').value) || 0),
    // o preço NÃO vai: quem decide é o servidor
    items: [...pos.cart.values()].map((l) => ({ product_id: l.product.id, quantity: l.quantity })),
  };

  let failure = null;

  try {
    const { data: sale } = await api('/sales', { method: 'POST', body });

    pos.cart.clear();
    $('pos-customer').value = '';
    $('pos-discount').value = '0';
    renderCart();

    toast(`Venda ${sale.code} concluída: ${money(sale.total)}`);
    await Promise.all([posLoad($('pos-search').value.trim()), loadSalesHistory()]);
  } catch (err) {
    failure = err;
  }

  pos.busy = false;
  updateCart();
  if (failure && failure.status !== 401) showPosError(errorMessages(failure).join(' '));
});

/* ----- últimas vendas ----- */

async function loadSalesHistory() {
  try {
    const res = await api('/sales?per_page=10');

    if (res.data.length === 0) {
      $('sale-rows').innerHTML = '<tr><td colspan="7" class="empty">Nenhuma venda ainda.</td></tr>';
      return;
    }

    $('sale-rows').innerHTML = res.data
      .map((s) => `
        <tr>
          <td>${esc(s.code)}</td>
          <td>${dateTime(s.sold_at)}</td>
          <td>${esc(s.customer_name ?? '—')}</td>
          <td>${esc(PAYMENT[s.payment_method] ?? s.payment_method)}</td>
          <td class="num">${money(s.total)}</td>
          <td>${statusBadge(SALE_STATUS, s.status)}</td>
                <td class="actions">${
                s.status === 'completed' && isAdmin()
              ? `<button class="btn small danger" data-id="${s.id}" data-code="${esc(s.code)}">Cancelar</button>`
              : ''
          }</td>
        </tr>`)
      .join('');
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
  }
}

$('sale-rows').addEventListener('click', async (e) => {
  const button = e.target.closest('button[data-id]');
  if (!button) return;

  if (!confirm(`Cancelar a venda ${button.dataset.code}?\nO estoque será devolvido.`)) return;

  button.disabled = true;
  try {
    await api(`/sales/${button.dataset.id}/cancel`, { method: 'POST' });
    toast('Venda cancelada. Estoque devolvido.');
    await Promise.all([loadSalesHistory(), posLoad($('pos-search').value.trim())]);
  } catch (err) {
    button.disabled = false;
    if (err.status !== 401) toast(err.message, true);
  }
});

/* ----- registro da página ----- */

PAGE_LOADERS.sales = () => Promise.all([posLoad($('pos-search').value.trim()), loadSalesHistory()]);

renderCart();
