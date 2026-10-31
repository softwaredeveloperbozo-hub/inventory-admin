/* ---------- página de fornecedores ---------- */

const sup = {
  page: 1,
  search: '',
  active: '',
  seq: 0,
  rows: new Map(),
  editingId: null,
};

async function loadSuppliers() {
  const seq = ++sup.seq;

  const params = new URLSearchParams({ page: sup.page, per_page: 10 });
  if (sup.search) params.set('search', sup.search);
  if (sup.active !== '') params.set('is_active', sup.active);

  try {
    const res = await api(`/suppliers?${params}`);
    if (seq !== sup.seq) return; // resposta antiga: descarta

    // excluiu o último item da página: volta uma página
    if (res.data.length === 0 && sup.page > 1) {
      sup.page = Math.max(res.meta.last_page, 1);
      return loadSuppliers();
    }

    sup.rows = new Map(res.data.map((s) => [s.id, s]));
    renderSuppliers(res.data);

    $('supplier-pager-info').textContent = res.meta.total === 0 ? '' : `${res.meta.from}–${res.meta.to} de ${res.meta.total}`;
    $('supplier-prev').disabled = res.meta.current_page <= 1;
    $('supplier-next').disabled = res.meta.current_page >= res.meta.last_page;
  } catch (err) {
    if (seq === sup.seq && err.status !== 401) toast(err.message, true);
  }
}

function renderSuppliers(rows) {
  if (rows.length === 0) {
    $('supplier-rows').innerHTML = '<tr><td colspan="6" class="empty">Nenhum fornecedor encontrado.</td></tr>';
    return;
  }

  $('supplier-rows').innerHTML = rows
    .map((s) => {
      const used = (s.purchases_count ?? 0) > 0;
      const contact = [s.phone, s.email].filter(Boolean).map((v) => esc(v)).join('<br>') || '—';
      const badge = s.is_active
        ? '<span class="badge ok">Ativo</span>'
        : '<span class="badge off">Inativo</span>';

      const remove = isAdmin()
        ? `<button class="btn small danger" data-action="delete" data-id="${s.id}" ${
            used ? 'disabled title="Possui compras: desative-o em vez de excluir"' : ''
          }>Excluir</button>`
        : '';

      return `
        <tr>
          <td>${esc(s.name)}</td>
          <td>${esc(s.tax_id ?? '—')}</td>
          <td>${contact}</td>
          <td class="num">${s.purchases_count ?? 0}</td>
          <td>${badge}</td>
          <td class="actions">
            <button class="btn small" data-action="edit" data-id="${s.id}">Editar</button>
            <button class="btn small" data-action="toggle" data-id="${s.id}">${s.is_active ? 'Desativar' : 'Ativar'}</button>
            ${remove}
          </td>
        </tr>`;
    })
    .join('');
}

/* ----- busca, filtro e paginação ----- */

const searchSuppliers = debounce((term) => {
  sup.search = term;
  sup.page = 1;
  loadSuppliers();
}, 300);

$('supplier-search').addEventListener('input', (e) => searchSuppliers(e.target.value.trim()));

$('supplier-active').addEventListener('change', (e) => {
  sup.active = e.target.value;
  sup.page = 1;
  loadSuppliers();
});

$('supplier-prev').addEventListener('click', () => { sup.page--; loadSuppliers(); });
$('supplier-next').addEventListener('click', () => { sup.page++; loadSuppliers(); });

/* ----- criar e editar ----- */

function showSupplierErrors(messages) {
  const el = $('s-errors');
  el.innerHTML = messages.map((m) => `<li>${esc(m)}</li>`).join('');
  el.classList.toggle('hidden', messages.length === 0);
}

function openSupplierForm(supplier = null) {
  sup.editingId = supplier ? supplier.id : null;

  $('supplier-title').textContent = supplier ? 'Editar fornecedor' : 'Novo fornecedor';
  $('s-name').value = supplier?.name ?? '';
  $('s-tax').value = supplier?.tax_id ?? '';
  $('s-phone').value = supplier?.phone ?? '';
  $('s-email').value = supplier?.email ?? '';
  $('s-address').value = supplier?.address ?? '';
  $('s-active').checked = supplier ? supplier.is_active : true;

  showSupplierErrors([]);
  $('supplier-dialog').showModal();
  $('s-name').focus();
}

$('new-supplier-btn').addEventListener('click', () => openSupplierForm());
$('s-cancel').addEventListener('click', () => $('supplier-dialog').close());

$('supplier-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = e.submitter;
  button.disabled = true;

  // campos opcionais vazios viram null: vários fornecedores podem ficar sem NIF
  const body = {
    name: $('s-name').value.trim(),
    tax_id: $('s-tax').value.trim() || null,
    phone: $('s-phone').value.trim() || null,
    email: $('s-email').value.trim() || null,
    address: $('s-address').value.trim() || null,
    is_active: $('s-active').checked,
  };

  const editing = sup.editingId !== null;

  try {
    await api(editing ? `/suppliers/${sup.editingId}` : '/suppliers', {
      method: editing ? 'PUT' : 'POST',
      body,
    });

    $('supplier-dialog').close();
    toast(editing ? 'Fornecedor atualizado.' : 'Fornecedor criado.');
    await loadSuppliers();
  } catch (err) {
    if (err.status !== 401) showSupplierErrors(errorMessages(err));
  } finally {
    button.disabled = false;
  }
});

/* ----- ativar, desativar, editar e excluir ----- */

$('supplier-rows').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-action]');
  if (!button) return;

  const supplier = sup.rows.get(Number(button.dataset.id));
  if (!supplier) return;

  if (button.dataset.action === 'edit') openSupplierForm(supplier);
  if (button.dataset.action === 'toggle') toggleSupplier(supplier, button);
  if (button.dataset.action === 'delete') removeSupplier(supplier);
});

async function toggleSupplier(supplier, button) {
  const next = !supplier.is_active;

  if (!next && !confirm(`Desativar "${supplier.name}"?\nEle deixa de aparecer em compras novas. O histórico é mantido.`)) return;

  button.disabled = true;

  try {
    // PATCH: só o campo que mudou
    await api(`/suppliers/${supplier.id}`, { method: 'PATCH', body: { is_active: next } });
    toast(next ? 'Fornecedor ativado.' : 'Fornecedor desativado.');
    await loadSuppliers();
  } catch (err) {
    button.disabled = false;
    if (err.status !== 401) toast(err.message, true);
  }
}

async function removeSupplier(supplier) {
  if (!confirm(`Excluir o fornecedor "${supplier.name}"?`)) return;

  try {
    await api(`/suppliers/${supplier.id}`, { method: 'DELETE' });
    toast('Fornecedor excluído.');
    await loadSuppliers();
  } catch (err) {
    // 409 (tem compras) e 403 (sem permissão) chegam aqui com a mensagem do servidor
    if (err.status !== 401) toast(err.message, true);
    await loadSuppliers();
  }
}

PAGE_LOADERS.suppliers = loadSuppliers;
