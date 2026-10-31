/* ---------- página de configurações (a minha conta) ---------- */

const PROFILE_LABEL = { admin: 'Administrador', operator: 'Operador' };

function showCfgErrors(id, messages) {
  const el = $(id);
  el.innerHTML = messages.map((m) => `<li>${esc(m)}</li>`).join('');
  el.classList.toggle('hidden', messages.length === 0);
}

async function loadConfig() {
  try {
    const user = await api('/auth/me');
    showApp(user); // mantém sidebar e navbar em dia

    $('cfg-name').value = user.name;
    $('cfg-email').value = user.email;
    $('cfg-role').value = PROFILE_LABEL[user.role] ?? user.role;
  } catch (err) {
    if (err.status !== 401) toast(err.message, true);
  }

  $('cfg-password-form').reset();
  showCfgErrors('cfg-profile-errors', []);
  showCfgErrors('cfg-password-errors', []);
}

/* ----- os meus dados ----- */

$('cfg-profile-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = e.submitter;
  button.disabled = true;

  try {
    const user = await api('/profile', {
      method: 'PUT',
      body: {
        name: $('cfg-name').value.trim(),
        email: $('cfg-email').value.trim().toLowerCase(),
      },
    });

    showApp(user);
    showCfgErrors('cfg-profile-errors', []);
    toast('Dados atualizados.');
  } catch (err) {
    if (err.status !== 401) showCfgErrors('cfg-profile-errors', errorMessages(err));
  } finally {
    button.disabled = false;
  }
});

/* ----- alterar a senha ----- */

$('cfg-password-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  const current = $('cfg-current').value;
  const next = $('cfg-new').value;
  const again = $('cfg-confirm').value;

  if (next !== again) {
    return showCfgErrors('cfg-password-errors', ['A confirmação não coincide com a senha nova.']);
  }
  if (next === current) {
    return showCfgErrors('cfg-password-errors', ['A senha nova tem de ser diferente da atual.']);
  }

  const button = e.submitter;
  button.disabled = true;

  try {
    await api('/profile/password', {
      method: 'PUT',
      body: { current_password: current, password: next, password_confirmation: again },
    });

    // o servidor encerrou esta sessão: entra-se de novo com a senha nova
    $('cfg-password-form').reset();
    localStorage.removeItem(TOKEN_KEY);
    showLogin('Senha alterada. Entre novamente com a senha nova.');
  } catch (err) {
    if (err.status !== 401) showCfgErrors('cfg-password-errors', errorMessages(err));
  } finally {
    button.disabled = false;
  }
});

PAGE_LOADERS.config = loadConfig;
