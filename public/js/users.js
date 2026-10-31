/* ---------- página de usuários (só administrador) ---------- */

const USER_ROLE = {
  admin: ['Administrador', 'admin'],
  operator: ['Operador', 'off'],
};

const usr = {
  page: 1,
  search: '',
  role: '',
  active: '',
  seq: 0,
  rows: new Map(),
  editingId: null,
};

async function loadUsers() {
  const seq = ++usr.seq;

  const params = new URLSearchParams({ page: usr.page, per_page: 10 });
  if (usr.search) params.set('search', usr.search);
  if (usr.role) params.set('role', usr.role);
  if (usr.active !== '') params.set('is_active', usr.active);

  try {
    const res = await api(`/users?${params}`);
    if (seq !== usr.seq) return; // resposta antiga: descarta

    if (res.data.length === 0 && usr.page > 1) {
      usr.page = Math.max(res.meta.last_page, 1);
      return loadUsers();
    }

    usr.rows = new Map(res.data.map((u) => [u.id, u]));
    renderUsers(res.data);

    $('user-pager-info').textContent = res.meta.total === 0 ? '' : `${res.meta.from}–${res.meta.to} de ${res.meta.total}`;
    $('user-prev').disabled = res.meta.current_page <= 1;
    $('user-next').disabled = res.meta.current_page >= res.meta.last_page;
  } catch (err) {
    if (seq === usr.seq && err.status !== 401) toast(err.message, true);
  }
}

function renderUsers(rows) {
  if (rows.length === 0) {
    $('user-rows').innerHTML = '<tr><td colspan="6" class="empty">Nenhum usuário encontrado.</td></tr>';
    return;
  }

  $('user-rows').innerHTML = rows
    .map((u) => {
      const toggle = u.is_me
        ? ''
        : `<button class="btn small" data-action="toggle" data-id="${u.id}">${u.is_active ? 'Desativar' : 'Ativar'}</button>`;

      return `
        <tr>
          <td>${esc(u.name)}${u.is_me ? ' <span class="muted">(você)</span>' : ''}</td>
          <td>${esc(u.email)}</td>
          <td>${statusBadge(USER_ROLE, u.role)}</td>
          <td>${u.is_active ? '<span class="badge ok">Ativo</span>' : '<span class="badge off">Inativo</span>'}</td>
          <td>${dateTime(u.created_at)}</td>
          <td class="actions">
            <button class="btn small" data-action="edit" data-id="${u.id}">Editar</button>
            ${toggle}
          </td>
        </tr>`;
    })
    .join('');
}

/* ----- busca, filtros e paginação ----- */

const searchUsers = debounce((term) => {
  usr.search = term;
  usr.page = 1;
  loadUsers();
}, 300);

$('user-search').addEventListener('input', (e) => searchUsers(e.target.value.trim()));

$('user-role').addEventListener('change', (e) => {
  usr.role = e.target.value;
  usr.page = 1;
  loadUsers();
});

$('user-active').addEventListener('change', (e) => {
  usr.active = e.target.value;
  usr.page = 1;
  loadUsers();
});

$('user-prev').addEventListener('click', () => { usr.page--; loadUsers(); });
$('user-next').addEventListener('click', () => { usr.page++; loadUsers(); });

/* ----- criar e editar ----- */

function showUserErrors(messages) {
  const el = $('u-errors');
  el.innerHTML = messages.map((m) => `<li>${esc(m)}</li>`).join('');
  el.classList.toggle('hidden', messages.length === 0);
}

function openUserForm(user = null) {
  usr.editingId = user ? user.id : null;
  const self = Boolean(user?.is_me);

  $('user-title').textContent = user ? 'Editar usuário' : 'Novo usuário';
  $('u-name').value = user?.name ?? '';
  $('u-email').value = user?.email ?? '';
  $('u-role').value = user?.role ?? 'operator';
  $('u-active').checked = user ? user.is_active : true;

  $('u-password').value = '';
  $('u-password').type = 'password';
  $('u-password').required = !user;
  $('u-show').checked = false;

  // ninguém rebaixa nem desativa a própria conta, e a própria senha muda em Configurações
  $('u-role').disabled = self;
  $('u-active').disabled = self;
  $('u-password').disabled = self;
  $('u-show').disabled = self;

  if (self) {
    $('u-hint').textContent = 'Esta é a sua conta: não pode alterar o seu perfil nem desativá-la. Para mudar a senha, use Configurações.';
  } else if (user) {
    $('u-hint').textContent = 'Deixe a senha em branco para manter a atual. Se a preencher, a pessoa passa a entrar com a senha nova.';
  } else {
    $('u-hint').textContent = 'A pessoa entra com este email e esta senha. Peça-lhe que a altere em Configurações.';
  }

  showUserErrors([]);
  $('user-dialog').showModal();
  $('u-name').focus();
}

$('new-user-btn').addEventListener('click', () => openUserForm());
$('u-cancel').addEventListener('click', () => $('user-dialog').close());

$('u-show').addEventListener('change', (e) => {
  $('u-password').type = e.target.checked ? 'text' : 'password';
});

$('user-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = e.submitter;
  button.disabled = true;

  const body = {
    name: $('u-name').value.trim(),
    email: $('u-email').value.trim().toLowerCase(),
    role: $('u-role').value,
    is_active: $('u-active').checked,
  };

  // em branco = manter a senha atual
  const password = $('u-password').value;
  if (password && !$('u-password').disabled) body.password = password;

  const editing = usr.editingId !== null;
  const editedMyself = editing && usr.rows.get(usr.editingId)?.is_me;

  try {
    await api(editing ? `/users/${usr.editingId}` : '/users', {
      method: editing ? 'PUT' : 'POST',
      body,
    });

    $('user-dialog').close();
    toast(editing ? 'Usuário atualizado.' : 'Usuário criado.');

    if (editedMyself) showApp(await api('/auth/me')); // nome novo na sidebar e na navbar
    await loadUsers();
  } catch (err) {
    if (err.status !== 401) showUserErrors(errorMessages(err));
  } finally {
    button.disabled = false;
  }
});

/* ----- editar e ativar/desativar (delegação de eventos) ----- */

$('user-rows').addEventListener('click', (e) => {
  const button = e.target.closest('button[data-action]');
  if (!button) return;

  const user = usr.rows.get(Number(button.dataset.id));
  if (!user) return;

  if (button.dataset.action === 'edit') openUserForm(user);
  if (button.dataset.action === 'toggle') toggleUser(user, button);
});

async function toggleUser(user, button) {
  const next = !user.is_active;

  if (!next && !confirm(`Desativar "${user.name}"?\nA pessoa perde o acesso imediatamente.`)) return;

  button.disabled = true;

  try {
    await api(`/users/${user.id}`, { method: 'PATCH', body: { is_active: next } });
    toast(next ? 'Usuário ativado.' : 'Usuário desativado.');
    await loadUsers();
  } catch (err) {
    button.disabled = false;
    if (err.status !== 401) toast(err.message, true);
  }
}

PAGE_LOADERS.users = loadUsers;
