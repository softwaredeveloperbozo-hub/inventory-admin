const API = '/api';
const TOKEN_KEY = 'inventory_token';

const $ = (id) => document.getElementById(id);

const state = {
  user: null,
  page: 1,
  search: '',
  categoryId: '',
  editingId: null,
  requestSeq: 0,
  products: new Map(),
};

/* ---------- utilitários ---------- */

class ApiError extends Error {
  constructor(status, message, errors = null) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

// Escapa HTML: impede que um nome como <script> seja executado na tabela
function esc(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

const money = (n) =>
  new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' Kz';

const qty = (n) => new Intl.NumberFormat('pt-PT', { maximumFractionDigits: 3 }).format(n);

const initials = (name) =>
  String(name ?? '').trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase() ?? '').join('');

const ROLE_LABEL = { admin: 'admin', operator: 'operador' };
const isAdmin = () => state.user?.role === 'admin';

const dateTime = (iso) =>
  iso ? new Date(iso).toLocaleString('pt-PT', { dateStyle: 'short', timeStyle: 'short' }) : '—';

const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
const round3 = (n) => Math.round((n + Number.EPSILON) * 1000) / 1000;

function debounce(fn, ms = 300) {
  let timer;
  const wrapped = (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  };
  wrapped.cancel = () => clearTimeout(timer);
  return wrapped;
}

// Um 422 de validação traz o objeto "errors"; os outros erros só trazem "message"
function errorMessages(err) {
  return err.errors ? Object.values(err.errors).flat() : [err.message];
}

function statusBadge(map, status) {
  const [label, css] = map[status] ?? [status, 'off'];
  return `<span class="badge ${css}">${esc(label)}</span>`;
}

async function fetchProducts(term = '', limit = 8) {
  const params = new URLSearchParams({ per_page: limit });
  if (term) params.set('search', term);
  const res = await api(`/products?${params}`);
  return res.data;
}

let toastTimer;
function toast(message, isError = false) {
  const el = $('toast');
  el.textContent = message;
  el.className = 'toast' + (isError ? ' error-toast' : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.add('hidden'), 3500);
}

/* ---------- chamadas à API ---------- */

async function api(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json' };
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  const res = await fetch(API + path, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401 && path !== '/auth/login') {
    localStorage.removeItem(TOKEN_KEY);
    showLogin('Sessão expirada. Entre novamente.');
    throw new ApiError(401, 'Sessão expirada.');
  }

  const data = res.status === 204 ? null : await res.json().catch(() => null);

  if (!res.ok) {
    throw new ApiError(res.status, data?.message || 'Erro inesperado.', data?.errors);
  }

  // qualquer alteração pode mudar os avisos (estoque, compras, vendas)
  if (method !== 'GET' && !path.startsWith('/auth/')) refreshNotifications();

  return data;
}

/* ---------- páginas ---------- */

const PAGES = ['dashboard', 'products', 'purchases', 'sales', 'categories', 'suppliers', 'users', 'config'];
const ADMIN_PAGES = ['users'];

// cada página registra aqui a função que carrega os seus dados
const PAGE_LOADERS = {
  dashboard: () => loadDashboard(),
  products: () => loadProducts(),
};

function currentPage() {
  const name = location.hash.replace('#', '');
  return PAGES.includes(name) ? name : 'dashboard';
}

async function route() {
  const page = currentPage();

  // páginas só de administrador: o operador volta ao painel
  if (ADMIN_PAGES.includes(page) && !isAdmin()) {
    location.hash = '#dashboard';
    return;
  }

  PAGES.forEach((name) => {
    $(`page-${name}`).classList.toggle('hidden', name !== page);
  });

  document.querySelectorAll('.nav a').forEach((link) => {
    const active = link.dataset.page === page;
    link.classList.toggle('active', active);
    if (active) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });

  const loader = PAGE_LOADERS[page];
  if (loader) await loader();
}

window.addEventListener('hashchange', () => {
  if (!$('app-view').classList.contains('hidden')) route();
});

/* ---------- login e sessão ---------- */

function showLogin(message = '') {
  state.user = null;
  stopNavbar();
  $('app-view').classList.add('hidden');
  $('login-view').classList.remove('hidden');
  const el = $('login-error');
  el.textContent = message;
  el.classList.toggle('hidden', !message);
}

function showApp(user) {
  state.user = user;
  $('login-view').classList.add('hidden');
  $('app-view').classList.remove('hidden');
  $('user-name').innerHTML =
    `<strong>${esc(user.name)}</strong><small>${esc(ROLE_LABEL[user.role] ?? user.role)}</small>`;

  $('navbar-user').textContent = initials(user.name);
  $('navbar-greeting').textContent = `Olá, ${user.name.trim().split(/\s+/)[0]}`;

  // links e blocos que só o administrador vê
  document.querySelectorAll('.admin-only').forEach((el) => el.classList.toggle('hidden', !isAdmin()));
}

async function startApp(user) {
  showApp(user);
  startNavbar();
  if (!$('month').value) $('month').value = currentMonth();
  await loadCategories();
  await route();
}

$('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = e.submitter;
  button.disabled = true;

  try {
    const data = await api('/auth/login', {
      method: 'POST',
      body: { email: $('login-email').value.trim().toLowerCase(), password: $('login-password').value },
    });
    localStorage.setItem(TOKEN_KEY, data.access_token);
    $('login-password').value = '';
    await startApp(data.user);
  } catch (err) {
    showLogin(err.status === 429 ? 'Muitas tentativas. Aguarde um minuto.' : err.message);
  } finally {
    button.disabled = false;
  }
});

$('logout-btn').addEventListener('click', async () => {
  try {
    await api('/auth/logout', { method: 'POST' });
  } catch (err) {
    // se o token já era inválido, segue para o login do mesmo jeito
  }
  localStorage.removeItem(TOKEN_KEY);
  showLogin();
});

/* ---------- categorias ---------- */

async function loadCategories() {
  const { data } = await api('/categories');
  const options = data.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');

  const filter = $('filter-category');
  const previous = state.categoryId;

  filter.innerHTML = '<option value="">Todas as categorias</option>' + options;
  filter.value = previous;

  // a categoria escolhida no filtro foi excluída: limpa o filtro
  if (filter.value !== previous) state.categoryId = '';

  $('f-category').innerHTML = '<option value="">Sem categoria</option>' + options;
}

/* ---------- lista de produtos ---------- */

async function loadProducts() {
  const seq = ++state.requestSeq;

  const params = new URLSearchParams({ page: state.page, per_page: 10 });
  if (state.search) params.set('search', state.search);
  if (state.categoryId) params.set('category_id', state.categoryId);

  try {
    const res = await api(`/products?${params}`);
    if (seq !== state.requestSeq) return; // chegou uma resposta antiga: descarta

    // apagou o último item da página: volta uma página
    if (res.data.length === 0 && state.page > 1) {
      state.page = Math.max(res.meta.last_page, 1);
      return loadProducts();
    }

    renderRows(res.data);
    renderPager(res.meta);
  } catch (err) {
    if (seq === state.requestSeq && err.status !== 401) toast(err.message, true);
  }
}

function renderRows(products) {
  state.products = new Map(products.map((p) => [p.id, p]));

  if (products.length === 0) {
    $('rows').innerHTML = '<tr><td colspan="7" class="empty">Nenhum produto encontrado.</td></tr>';
    return;
  }

  $('rows').innerHTML = products
    .map((p) => {
      const quantity = p.stock ? p.stock.quantity : 0;

      let badge = '<span class="badge ok">OK</span>';
      if (!p.is_active) badge = '<span class="badge off">Inativo</span>';
      else if (quantity <= 0) badge = '<span class="badge out">Sem estoque</span>';
      else if (p.stock && p.stock.is_low) badge = '<span class="badge low">Estoque baixo</span>';

      const price = p.price ? money(p.price.sale_price) : '<span class="muted">sem preço</span>';

      return `
        <tr>
          <td>${esc(p.sku)}</td>
          <td>${esc(p.name)}</td>
          <td>${esc(p.category?.name ?? '—')}</td>
          <td class="num">${qty(quantity)}</td>
          <td class="num">${price}</td>
          <td>${badge}</td>
          <td class="actions">
            <button class="btn small" data-action="edit" data-id="${p.id}">Editar</button>
            ${isAdmin() ? `<button class="btn small danger" data-action="delete" data-id="${p.id}">Excluir</button>` : ''}
          </td>
        </tr>`;
    })
    .join('');
}

function renderPager(meta) {
  $('pager-info').textContent = meta.total === 0 ? '' : `${meta.from}–${meta.to} de ${meta.total}`;
  $('prev-btn').disabled = meta.current_page <= 1;
  $('next-btn').disabled = meta.current_page >= meta.last_page;
}

$('prev-btn').addEventListener('click', () => {
  state.page--;
  loadProducts();
});

$('next-btn').addEventListener('click', () => {
  state.page++;
  loadProducts();
});

/* ---------- busca em tempo real ---------- */

let searchTimer;
$('search').addEventListener('input', (e) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    state.search = e.target.value.trim();
    state.page = 1;
    loadProducts();
  }, 300);
});

$('filter-category').addEventListener('change', (e) => {
  state.categoryId = e.target.value;
  state.page = 1;
  loadProducts();
});

/* ---------- criar e editar ---------- */

function showFormErrors(messages) {
  const el = $('form-errors');
  el.innerHTML = messages.map((m) => `<li>${esc(m)}</li>`).join('');
  el.classList.toggle('hidden', messages.length === 0);
}

function openForm(product = null) {
  state.editingId = product ? product.id : null;

  $('dialog-title').textContent = product ? 'Editar produto' : 'Novo produto';
  $('f-sku').value = product?.sku ?? '';
  $('f-barcode').value = product?.barcode ?? '';
  $('f-name').value = product?.name ?? '';
  $('f-category').value = product?.category?.id ?? '';
  $('f-tax').value = product?.tax_rate ?? 0;
  $('f-description').value = product?.description ?? '';
  $('f-active').checked = product ? product.is_active : true;

  showFormErrors([]);
  $('product-dialog').showModal();
  $('f-sku').focus();
}

$('new-btn').addEventListener('click', () => openForm());
$('cancel-btn').addEventListener('click', () => $('product-dialog').close());

$('product-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = e.submitter;
  button.disabled = true;

  const body = {
    sku: $('f-sku').value.trim(),
    barcode: $('f-barcode').value.trim() || null,
    name: $('f-name').value.trim(),
    category_id: $('f-category').value ? Number($('f-category').value) : null,
    tax_rate: Number($('f-tax').value || 0),
    description: $('f-description').value.trim() || null,
    is_active: $('f-active').checked,
  };

  const editing = state.editingId !== null;

  try {
    await api(editing ? `/products/${state.editingId}` : '/products', {
      method: editing ? 'PUT' : 'POST',
      body,
    });
    $('product-dialog').close();
    toast(editing ? 'Produto atualizado.' : 'Produto criado.');
    await loadProducts(); // o painel se atualiza sozinho quando você voltar a ele
  } catch (err) {
    if (err.status === 422 && err.errors) showFormErrors(Object.values(err.errors).flat());
    else if (err.status !== 401) showFormErrors([err.message]);
  } finally {
    button.disabled = false;
  }
});

/* ---------- editar e excluir (delegação de eventos) ---------- */

$('rows').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-action]');
  if (!button) return;

  const product = state.products.get(Number(button.dataset.id));
  if (!product) return;

  if (button.dataset.action === 'edit') openForm(product);
  if (button.dataset.action === 'delete') removeProduct(product);
});

async function removeProduct(product) {
  if (!confirm(`Excluir "${product.name}"?\nO histórico de compras e vendas é mantido.`)) return;

  try {
    await api(`/products/${product.id}`, { method: 'DELETE' });
    toast('Produto excluído.');
    await loadProducts();
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
  }
}

/* ---------- dashboard ---------- */

const COLORS = { ink: '#1459da', slate: '#1459da', tag: '#1459da', teal: '#1459da' };

if (typeof Chart !== 'undefined') {
  Chart.defaults.font.family = "'IBM Plex Sans', system-ui, sans-serif";
  Chart.defaults.font.size = 12;
  Chart.defaults.color = '#5b6676';
  Chart.defaults.borderColor = '#e3e5e0';
}

// a barra do produto mais vendido ganha a cor de destaque
const barFills = (n) => Array.from({ length: n }, (_, i) => (i === 0 ? COLORS.tag : COLORS.slate));
const barEdges = (n) => Array.from({ length: n }, (_, i) => (i === 0 ? COLORS.ink : 'transparent'));

let topChart;
let dayChart;
let dashSeq = 0;

function currentMonth() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

async function loadDashboard() {
  const seq = ++dashSeq;
  const month = $('month').value || currentMonth();

  try {
    const [summary, top, days] = await Promise.all([
      api(`/dashboard/summary?month=${month}`),
      api(`/dashboard/top-products?month=${month}&limit=5`),
      api(`/dashboard/sales-by-day?month=${month}`),
    ]);
    if (seq !== dashSeq) return; // resposta antiga: descarta

    renderCards(summary.data);
    renderTopChart(top.data);
    renderDayChart(days.data);
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
  }
}

function renderCards(s) {
  const items = [
    ['Vendas', s.sales_count, ''],
    ['Faturamento', money(s.revenue), ''],
    ['Lucro', money(s.profit), s.profit < 0 ? 'bad' : ''],
    ['Ticket médio', money(s.average_ticket), ''],
    ['Estoque baixo', s.low_stock_count, s.low_stock_count > 0 ? 'warn' : ''],
    ['Valor em estoque (custo)', money(s.stock_value.at_cost), ''],
  ];

  $('cards').innerHTML = items
    .map(([label, value, tone]) => `<div class="stat ${tone}"><span>${label}</span><strong>${value}</strong></div>`)
    .join('');
}

function renderTopChart(rows) {
  $('top-empty').classList.toggle('hidden', rows.length > 0);

  const labels = rows.map((r) => r.name);
  const values = rows.map((r) => r.quantity_sold);
  const fills = barFills(rows.length);
  const edges = barEdges(rows.length);

  if (!topChart) {
    topChart = new Chart($('top-chart'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Unidades vendidas',
          data: values,
          backgroundColor: fills,
          borderColor: edges,
          borderWidth: 1.5,
          borderRadius: 4,
          barPercentage: 0.7,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (ctx) => `${qty(ctx.parsed.x)} un.` } },
        },
        scales: {
          x: { beginAtZero: true },
          y: { grid: { display: false } },
        },
      },
    });
    return;
  }

  const dataset = topChart.data.datasets[0];
  topChart.data.labels = labels;
  dataset.data = values;
  dataset.backgroundColor = fills;
  dataset.borderColor = edges;
  topChart.update();
}

function renderDayChart(rows) {
  const labels = rows.map((r) => r.date.slice(8)); // só o dia: "07"
  const values = rows.map((r) => r.revenue);

  if (!dayChart) {
    dayChart = new Chart($('day-chart'), {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Faturamento',
          data: values,
          borderColor: COLORS.teal,
          backgroundColor: 'rgba(14, 124, 102, .10)',
          borderWidth: 2,
          fill: true,
          tension: 0.3,
          pointRadius: 0,
          pointHoverRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (ctx) => money(ctx.parsed.y) } },
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true },
        },
      },
    });
    return;
  }

  dayChart.data.labels = labels;
  dayChart.data.datasets[0].data = values;
  dayChart.update();
}

$('month').addEventListener('change', loadDashboard);

/* ---------- exportar (Excel e PDF) ---------- */

async function exportProducts(format) {
  const params = new URLSearchParams({ format });
  if (state.search) params.set('search', state.search);
  if (state.categoryId) params.set('category_id', state.categoryId);

  const button = $(`export-${format}`);
  button.disabled = true;

  try {
    const res = await fetch(`${API}/products/export?${params}`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${localStorage.getItem(TOKEN_KEY)}`,
      },
    });

    if (res.status === 401) {
      localStorage.removeItem(TOKEN_KEY);
      showLogin('Sessão expirada. Entre novamente.');
      return;
    }

    if (!res.ok) {
      const data = await res.json().catch(() => null);
      throw new Error(data?.message || 'Não foi possível exportar.');
    }

    const blob = await res.blob();
    const disposition = res.headers.get('Content-Disposition') || '';
    const filename = /filename="?([^";]+)"?/.exec(disposition)?.[1] || `produtos.${format}`;

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);

    toast('Exportação concluída.');
  } catch (err) {
    toast(err.message, true);
  } finally {
    button.disabled = false;
  }
}

$('export-xlsx').addEventListener('click', () => exportProducts('xlsx'));
$('export-pdf').addEventListener('click', () => exportProducts('pdf'));

/* ---------- arranque ---------- */

async function boot() {
  if (!localStorage.getItem(TOKEN_KEY)) {
    showLogin();
    return;
  }

  try {
    const user = await api('/auth/me');
    await startApp(user);
  } catch (err) {
    if (err.status !== 401) showLogin();
  }
}

document.addEventListener('DOMContentLoaded', boot);
