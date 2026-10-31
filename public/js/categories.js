/* ---------- página de categorias ---------- */

const cat = {
  search: '',
  seq: 0,
  rows: new Map(),
  editingId: null,
};

async function loadCategoriesPage() {
  const seq = ++cat.seq;

  const params = new URLSearchParams();
  if (cat.search) params.set('search', cat.search);

  try {
    const res = await api(`/categories?${params}`);
    if (seq !== cat.seq) return; // resposta antiga: descarta

    cat.rows = new Map(res.data.map((c) => [c.id, c]));
    renderCategories(res.data);
  } catch (err) {
    if (seq === cat.seq && err.status !== 401) toast(err.message, true);
  }
}

function renderCategories(rows) {
  if (rows.length === 0) {
    $('category-rows').innerHTML = '<tr><td colspan="4" class="empty">Nenhuma categoria encontrada.</td></tr>';
    return;
  }

  $('category-rows').innerHTML = rows
    .map((c) => {
      const used = (c.products_count ?? 0) > 0;

      // o servidor é quem decide: o botão desabilitado é só uma dica
      const remove = isAdmin()
        ? `<button class="btn small danger" data-action="delete" data-id="${c.id}" ${
            used ? 'disabled title="Possui produtos: mova-os para outra categoria antes de excluir"' : ''
          }>Excluir</button>`
        : '';

      return `
        <tr>
          <td>${esc(c.name)}</td>
          <td class="num">${c.products_count ?? 0}</td>
          <td>${dateTime(c.created_at)}</td>
          <td class="actions">
            <button class="btn small" data-action="edit" data-id="${c.id}">Editar</button>
            ${remove}
          </td>
        </tr>`;
    })
    .join('');
}

/* ----- busca ----- */

const searchCategories = debounce((term) => {
  cat.search = term;
  loadCategoriesPage();
}, 300);

$('category-search').addEventListener('input', (e) => searchCategories(e.target.value.trim()));

/* ----- criar e editar ----- */

function showCategoryErrors(messages) {
  const el = $('c-errors');
  el.innerHTML = messages.map((m) => `<li>${esc(m)}</li>`).join('');
  el.classList.toggle('hidden', messages.length === 0);
}

function openCategoryForm(category = null) {
  cat.editingId = category ? category.id : null;

  $('category-title').textContent = category ? 'Editar categoria' : 'Nova categoria';
  $('c-name').value = category?.name ?? '';

  showCategoryErrors([]);
  $('category-dialog').showModal();
  $('c-name').focus();
}

$('new-category-btn').addEventListener('click', () => openCategoryForm());
$('c-cancel').addEventListener('click', () => $('category-dialog').close());

$('category-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = e.submitter;
  button.disabled = true;

  const editing = cat.editingId !== null;

  try {
    await api(editing ? `/categories/${cat.editingId}` : '/categories', {
      method: editing ? 'PUT' : 'POST',
      body: { name: $('c-name').value.trim() },
    });

    $('category-dialog').close();
    toast(editing ? 'Categoria atualizada.' : 'Categoria criada.');

    // a lista da página e as listas suspensas de Produtos
    await Promise.all([loadCategoriesPage(), loadCategories()]);
  } catch (err) {
    if (err.status !== 401) showCategoryErrors(errorMessages(err));
  } finally {
    button.disabled = false;
  }
});

/* ----- editar e excluir (delegação de eventos) ----- */

$('category-rows').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-action]');
  if (!button) return;

  const category = cat.rows.get(Number(button.dataset.id));
  if (!category) return;

  if (button.dataset.action === 'edit') openCategoryForm(category);
  if (button.dataset.action === 'delete') removeCategory(category);
});

async function removeCategory(category) {
  if (!confirm(`Excluir a categoria "${category.name}"?`)) return;

  try {
    await api(`/categories/${category.id}`, { method: 'DELETE' });
    toast('Categoria excluída.');
    await Promise.all([loadCategoriesPage(), loadCategories()]);
  } catch (err) {
    // 409 (tem produtos) e 403 (sem permissão) chegam aqui com a mensagem do servidor
    if (err.status !== 401) toast(err.message, true);
    await loadCategoriesPage(); // atualiza a contagem que estava velha
  }
}

PAGE_LOADERS.categories = loadCategoriesPage;
