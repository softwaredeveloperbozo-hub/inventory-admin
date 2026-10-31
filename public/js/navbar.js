/* ---------- navbar e notificações ---------- */

const notif = { items: [], timer: null, open: false };

const SEVERITIES = ['danger', 'warning', 'info', 'success'];

// cada pessoa tem a sua própria lista de avisos já vistos
const seenStorageKey = () => `inventory_notif_seen:${state.user?.id ?? 'anon'}`;

function seenKeys() {
  try {
    return new Set(JSON.parse(localStorage.getItem(seenStorageKey()) || '[]'));
  } catch (err) {
    return new Set();
  }
}

function renderNotifications() {
  const seen = seenKeys();
  const unseen = notif.items.filter((n) => !seen.has(n.key)).length;

  $('notif-count').textContent = unseen > 9 ? '9+' : String(unseen);
  $('notif-count').classList.toggle('hidden', unseen === 0);
  $('notif-btn').setAttribute('aria-label', unseen ? `Notificações: ${unseen} novas` : 'Notificações');
  $('notif-seen').disabled = unseen === 0;

  if (notif.items.length === 0) {
    $('notif-list').innerHTML = '<li class="notif-empty">Tudo em ordem. Nenhum aviso por agora.</li>';
    return;
  }

  $('notif-list').innerHTML = notif.items
    .map((n) => {
      const severity = SEVERITIES.includes(n.severity) ? n.severity : 'info';

      return `
        <li>
          <a href="#${esc(n.page)}" class="notif-item ${seen.has(n.key) ? 'seen' : ''}">
            <span class="notif-dot ${severity}" aria-hidden="true"></span>
            <span class="notif-text">
              <strong>${esc(n.title)}</strong>
              <small>${esc(n.message)}</small>
            </span>
          </a>
        </li>`;
    })
    .join('');
}

async function loadNotifications() {
  // depois de sair, um pedido atrasado não pode mandar a pessoa de volta ao login
  if (!localStorage.getItem(TOKEN_KEY)) return;

  try {
    const res = await api('/notifications');
    notif.items = res.data;
    renderNotifications();
  } catch (err) {
    // o sino não deve incomodar: se falhar, tenta de novo no próximo ciclo
  }
}

// qualquer alteração (venda, compra, ajuste) pode mudar os avisos
const refreshNotifications = debounce(loadNotifications, 600);

/* ----- abrir e fechar o painel ----- */

function setNotificationsOpen(open) {
  notif.open = open;
  $('notif-panel').classList.toggle('hidden', !open);
  $('notif-btn').setAttribute('aria-expanded', String(open));
}

$('notif-btn').addEventListener('click', () => setNotificationsOpen(!notif.open));

document.addEventListener('click', (e) => {
  if (notif.open && !e.target.closest('.notif')) setNotificationsOpen(false);
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && notif.open) {
    setNotificationsOpen(false);
    $('notif-btn').focus();
  }
});

// clicar num aviso leva à página e fecha o painel
$('notif-list').addEventListener('click', (e) => {
  if (e.target.closest('a')) setNotificationsOpen(false);
});

$('notif-seen').addEventListener('click', () => {
  localStorage.setItem(seenStorageKey(), JSON.stringify(notif.items.map((n) => n.key)));
  renderNotifications();
});

/* ----- ciclo de vida ----- */

function renderNavbarDate() {
  const text = new Date().toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'long' });
  $('navbar-date').textContent = text.charAt(0).toUpperCase() + text.slice(1);
}

function startNavbar() {
  stopNavbar();
  renderNavbarDate();
  loadNotifications();
  notif.timer = setInterval(() => {
    if (!document.hidden) loadNotifications();
  }, 60000);
}

function stopNavbar() {
  clearInterval(notif.timer);
  notif.items = [];
  setNotificationsOpen(false);
  renderNotifications();
}
