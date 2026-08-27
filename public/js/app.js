import { api } from './api.js';
import {
  h, esc, clear, fmtDateTime, fmtRelative, fmtDuration, initials, toast, compressImage,
} from './util.js';

const state = {
  user: null,
  meta: null,
  view: 'richieste',
  filters: { status: 'attive', priorita: '', impianto: '', q: '', mine: false },
  detailId: null,
  es: null,
  refreshList: null,
};

const root = document.getElementById('app');

// ---- role helpers ----
const isTech = () => ['manutentore', 'admin'].includes(state.user?.role);
const isAdmin = () => state.user?.role === 'admin';

const statoInfo = (v) =>
  state.meta.stati.find((s) => s.value === v) || { value: v, label: v, color: '#78909c', done: false };
const prioInfo = (v) =>
  state.meta.priorita.find((p) => p.value === v) || { value: v, label: v, short: v, color: '#78909c' };

const statoBadge = (v) => {
  const s = statoInfo(v);
  return h('span', { class: 'badge', style: `background:${s.color}` }, h('span', { class: 'dot' }), s.label);
};
const prioBadge = (v) => {
  const p = prioInfo(v);
  return h('span', { class: 'badge', style: `background:${p.color}` }, p.short);
};

// =========================================================================
// Bootstrap
// =========================================================================
async function init() {
  try {
    const [{ user }, meta] = await Promise.all([api.me(), api.meta()]);
    state.meta = meta;
    if (!user) { renderLogin(); return; }
    state.user = user;
    connectEvents();
    renderApp();
  } catch {
    renderLogin();
  }
}

function connectEvents() {
  try {
    state.es?.close();
    const es = new EventSource('/api/events');
    state.es = es;
    const onChange = (ev) => {
      let data = {};
      try { data = JSON.parse(ev.data || '{}'); } catch {}
      if (ev.type === 'request:new' && isTech() && state.view === 'richieste') {
        toast('Nuova richiesta di manutenzione ricevuta', 'ok');
      }
      state.refreshList?.();
      if (state.detailId && (!data.id || data.id === state.detailId)) refreshDetail();
    };
    es.addEventListener('request:new', onChange);
    es.addEventListener('request:update', onChange);
    es.onerror = () => { /* browser auto-reconnects */ };
  } catch { /* SSE unsupported -> polling fallback below */ }
}

// =========================================================================
// Login
// =========================================================================
function renderLogin() {
  root.className = '';
  clear(root);
  const err = h('div', { class: 'inline-error', style: 'display:none' });
  const userInput = h('input', { type: 'text', name: 'username', placeholder: 'Username', autocomplete: 'username', required: true });
  const passInput = h('input', { type: 'password', name: 'password', placeholder: 'Password', autocomplete: 'current-password', required: true });
  const btn = h('button', { type: 'submit', class: 'btn btn-primary btn-lg btn-block' }, 'Accedi');

  const form = h('form', {
    onsubmit: async (e) => {
      e.preventDefault();
      err.style.display = 'none';
      btn.disabled = true; btn.textContent = 'Accesso…';
      try {
        const { user } = await api.login(userInput.value.trim(), passInput.value);
        state.user = user;
        state.meta = await api.meta();
        connectEvents();
        renderApp();
      } catch (ex) {
        err.textContent = ex.message;
        err.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Accedi';
      }
    },
  },
    h('div', { class: 'field' }, h('label', {}, 'Username'), userInput),
    h('div', { class: 'field' }, h('label', {}, 'Password'), passInput),
    err,
    btn,
  );

  root.append(
    h('div', { class: 'login-wrap' },
      h('div', { class: 'login-card' },
        h('div', { class: 'login-top' }),
        h('div', { class: 'login-inner' },
          h('div', { class: 'logo' }, '🔧'),
          h('h1', {}, 'Richiesta Manutenzione'),
          h('p', { class: 'sub' }, 'Accedi per gestire le richieste'),
          form,
          h('div', { class: 'login-hint' }, 'Accesso riservato al personale autorizzato.'),
        ),
      ),
    ),
  );
}

// =========================================================================
// App shell
// =========================================================================
function navItems() {
  const items = [{ key: 'richieste', label: 'Richieste', ic: '📋' }, { key: 'nuova', label: 'Nuova', ic: '➕' }];
  if (isAdmin()) items.push({ key: 'utenti', label: 'Utenti', ic: '👥' });
  return items;
}

function renderApp() {
  root.className = '';
  clear(root);

  const navBtn = (it) =>
    h('button', { class: state.view === it.key ? 'active' : '', onclick: () => setView(it.key) }, it.label);
  const mNavBtn = (it) =>
    h('button', { class: state.view === it.key ? 'active' : '', onclick: () => setView(it.key) },
      h('span', { class: 'ic' }, it.ic), it.label);

  const header = h('header', { class: 'topbar' },
    h('div', { class: 'topbar-inner' },
      h('div', { class: 'brand' }, h('span', { class: 'logo' }, '🔧'),
        h('div', {}, 'Manutenzione', h('small', {}, 'ASE'))),
      h('nav', { class: 'nav' }, navItems().map(navBtn)),
      h('div', { class: 'spacer' }),
      userMenu(),
    ),
  );

  const content = h('main', { class: 'container', id: 'content' });
  const mobileNav = h('nav', { class: 'mobile-nav' }, navItems().map(mNavBtn));

  root.append(header, content, mobileNav);
  renderView();
}

function setView(view) {
  state.view = view;
  state.refreshList = null;
  // refresh header nav active states
  document.querySelectorAll('.nav button, .mobile-nav button').forEach((b) => {
    b.classList.toggle('active', b.textContent.includes(navItems().find((n) => n.key === view)?.label || '###'));
  });
  renderView();
}

function renderView() {
  const c = document.getElementById('content');
  if (!c) return;
  clear(c);
  if (state.view === 'nuova') renderNuova(c);
  else if (state.view === 'utenti' && isAdmin()) renderUtenti(c);
  else { state.view = 'richieste'; renderRichieste(c); }
}

function userMenu() {
  const panel = h('div', { class: 'usermenu-panel', style: 'display:none' },
    h('div', { class: 'uinfo' },
      h('strong', {}, state.user.full_name),
      h('span', { class: 'role-badge' }, state.user.role)),
    h('button', { onclick: () => { panel.style.display = 'none'; openChangePassword(); } }, '🔑 Cambia password'),
    h('button', { onclick: async () => { await api.logout(); state.es?.close(); location.reload(); } }, '🚪 Esci'),
  );
  const btn = h('button', { class: 'usermenu-btn', onclick: (e) => {
    e.stopPropagation();
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
  } },
    h('span', { class: 'avatar' }, initials(state.user.full_name)),
    h('span', {}, state.user.full_name.split(' ')[0]),
  );
  document.addEventListener('click', () => { panel.style.display = 'none'; });
  return h('div', { class: 'usermenu' }, btn, panel);
}

// =========================================================================
// Requests list
// =========================================================================
function renderRichieste(c) {
  const statsRow = h('div', { class: 'stats' });
  const toolbar = buildToolbar();
  const listWrap = h('div', { class: 'cards' });
  c.append(statsRow, toolbar, listWrap);

  async function refresh() {
    try {
      const [{ requests }, stats] = await Promise.all([
        api.listRequests({
          status: state.filters.status,
          priorita: state.filters.priorita,
          impianto: state.filters.impianto,
          q: state.filters.q,
          mine: state.filters.mine ? '1' : '',
        }),
        api.stats(),
      ]);
      renderStats(statsRow, stats);
      renderList(listWrap, requests);
    } catch (e) {
      if (e.status === 401) return location.reload();
      toast(e.message, 'err');
    }
  }
  state.refreshList = refresh;
  refresh();
  startPolling();
}

function renderStats(row, stats) {
  clear(row);
  row.append(
    h('div', { class: 'stat' }, h('div', { class: 'n' }, stats.attive), h('div', { class: 'l' }, 'Richieste attive')),
    h('div', { class: 'stat alert' }, h('div', { class: 'n' }, stats.urgenti), h('div', { class: 'l' }, 'Urgenti (rosso)')),
    h('div', { class: 'stat' }, h('div', { class: 'n' }, stats.mie), h('div', { class: 'l' }, 'Le mie richieste')),
  );
}

function buildToolbar() {
  const f = state.filters;
  const search = h('input', {
    type: 'search', class: 'search', placeholder: '🔎 Cerca macchinario, reparto, operatore…', value: f.q,
    oninput: debounce((e) => { f.q = e.target.value.trim(); state.refreshList?.(); }, 300),
  });

  const statusChip = (val, label) =>
    h('button', { class: 'chip' + (f.status === val ? ' active' : ''), onclick: () => { f.status = val; state.refreshList?.(); refreshChips(); } }, label);
  const statusChips = h('div', { class: 'chip-row', dataset: { group: 'status' } },
    statusChip('attive', 'Attive'), statusChip('tutte', 'Tutte'), statusChip('chiuse', 'Chiuse'));

  const prioChip = (val, label, color) =>
    h('button', {
      class: 'chip' + (f.priorita === val ? ' active' : ''),
      style: f.priorita === val && color ? `background:${color};border-color:${color};color:#fff` : '',
      onclick: () => { f.priorita = f.priorita === val ? '' : val; state.refreshList?.(); refreshChips(); },
    }, label);
  const prioChips = h('div', { class: 'chip-row', dataset: { group: 'prio' } },
    ...state.meta.priorita.map((p) => prioChip(p.value, p.short, p.color)));

  const mineChip = h('button', { class: 'chip' + (f.mine ? ' active' : ''), dataset: { role: 'mine' },
    onclick: () => { f.mine = !f.mine; state.refreshList?.(); refreshChips(); } }, '👤 Le mie');

  const impSelect = h('select', { style: 'max-width:200px', onchange: (e) => { f.impianto = e.target.value; state.refreshList?.(); } },
    h('option', { value: '' }, 'Tutti gli impianti'),
    ...state.meta.impianti.map((i) => h('option', { value: i, selected: f.impianto === i }, i)));

  function refreshChips() {
    document.querySelectorAll('[data-group="status"] .chip').forEach((b, i) =>
      b.classList.toggle('active', ['attive', 'tutte', 'chiuse'][i] === f.status));
    document.querySelectorAll('[data-group="prio"] .chip').forEach((b, i) => {
      const p = state.meta.priorita[i];
      const on = f.priorita === p.value;
      b.classList.toggle('active', on);
      b.style.cssText = on ? `background:${p.color};border-color:${p.color};color:#fff` : '';
    });
    mineChip.classList.toggle('active', f.mine);
  }

  return h('div', {},
    h('div', { class: 'toolbar' }, search, mineChip, impSelect),
    h('div', { class: 'toolbar', style: 'margin-top:-6px' }, statusChips, prioChips),
  );
}

function renderList(wrap, requests) {
  clear(wrap);
  if (!requests.length) {
    wrap.append(h('div', { class: 'empty' }, h('div', { class: 'big' }, '📭'),
      h('div', {}, 'Nessuna richiesta trovata con questi filtri.')));
    return;
  }
  for (const r of requests) wrap.append(requestCard(r));
}

function requestCard(r) {
  const impLabel = r.impianto === 'Altro' && r.impianto_altro ? `Altro: ${r.impianto_altro}` : r.impianto;
  return h('div', { class: `rcard p-${r.priorita}`, onclick: () => openDetail(r.id) },
    h('div', { class: 'rcard-head' },
      h('h3', {}, r.macchinario),
      h('span', { class: 'rcard-id' }, `#${r.id}`)),
    h('div', { class: 'rcard-meta' },
      h('span', {}, '🏭 ', impLabel),
      r.reparto ? h('span', {}, '📍 ', r.reparto) : null,
      h('span', {}, '👤 ', r.operatore),
      h('span', {}, '🕒 ', fmtRelative(r.created_at)),
      r.n_attachments ? h('span', {}, '📷 ', String(r.n_attachments)) : null,
      r.assigned_to_name ? h('span', {}, '🔧 ', r.assigned_to_name) : null,
    ),
    h('div', { class: 'rcard-badges' }, prioBadge(r.priorita), statoBadge(r.status)),
  );
}

// =========================================================================
// Detail panel
// =========================================================================
async function openDetail(id) {
  state.detailId = id;
  const overlay = h('div', { class: 'overlay', id: 'detail-overlay',
    onclick: (e) => { if (e.target === overlay) closeDetail(); } });
  const panel = h('div', { class: 'panel' },
    h('div', { class: 'panel-head' },
      h('button', { class: 'iconbtn', onclick: closeDetail, title: 'Chiudi' }, '✕'),
      h('h2', {}, `Richiesta #${id}`)),
    h('div', { class: 'panel-body', id: 'detail-body' }, h('div', { class: 'muted' }, 'Caricamento…')));
  overlay.append(panel);
  document.body.append(overlay);
  document.body.style.overflow = 'hidden';
  await refreshDetail();
}

function closeDetail() {
  state.detailId = null;
  document.getElementById('detail-overlay')?.remove();
  document.body.style.overflow = '';
}

async function refreshDetail() {
  if (!state.detailId) return;
  const body = document.getElementById('detail-body');
  if (!body) return;
  try {
    const { request } = await api.getRequest(state.detailId);
    renderDetail(body, request);
  } catch (e) {
    if (e.status === 401) return location.reload();
    body && clear(body).append(h('div', { class: 'inline-error' }, e.message));
  }
}

function renderDetail(body, r) {
  clear(body);
  const impLabel = r.impianto === 'Altro' && r.impianto_altro ? `Altro: ${r.impianto_altro}` : r.impianto;

  body.append(
    h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center' }, statoBadge(r.status), prioBadge(r.priorita)),
    h('h2', { style: 'margin:12px 0 2px;font-size:20px' }, r.macchinario),
    h('div', { class: 'muted', style: 'font-size:13px' }, `${impLabel}${r.reparto ? ' · ' + r.reparto : ''}`),
  );

  const dl = (label, value) => h('div', { class: 'dl' }, h('dt', {}, label), h('dd', {}, value || '—'));
  const grid = h('div', { class: 'detail-grid' },
    dl('Operatore', r.operatore),
    dl('Impianto', impLabel),
    dl('Reparto', r.reparto),
    dl('Priorità', prioInfo(r.priorita).label),
    r.descrizione ? h('div', { class: 'full' }, dl('Descrizione evento', r.descrizione)) : null,
    r.note ? h('div', { class: 'full' }, dl('Note', r.note)) : null,
  );
  body.append(grid);

  // Timings
  const timing = h('div', { class: 'detail-grid', style: 'background:#faf9f4;border:1px solid var(--line);border-radius:10px;padding:12px 14px' },
    dl('Aperta il', fmtDateTime(r.created_at)),
    dl('Presa in carico', r.taken_at ? fmtDateTime(r.taken_at) : 'In attesa'),
    dl('Risolta il', r.resolved_at ? fmtDateTime(r.resolved_at) : '—'),
    dl('Tempo di risoluzione', r.resolved_at ? fmtDuration(r.created_at, r.resolved_at) : (statoInfo(r.status).done ? '—' : 'In corso')),
    r.assigned_to_name ? dl('Manutentore', r.assigned_to_name) : null,
  );
  body.append(timing);

  // Problem photos
  const problema = r.problema_attachments || [];
  body.append(h('div', { class: 'block-title' }, `📷 Foto del problema (${problema.length})`));
  body.append(photoGrid(problema));
  const canAddProblem = (r.created_by === state.user.id || isAdmin()) && !statoInfo(r.status).done;
  if (canAddProblem) body.append(quickPhotoAdd(r.id, 'problema'));

  // Timeline of interventions
  body.append(h('div', { class: 'block-title' }, '🔧 Interventi e stato'));
  if (!r.updates.length) {
    body.append(h('div', { class: 'muted', style: 'font-size:14px;margin-bottom:8px' },
      'Nessun intervento registrato. In attesa della presa in carico.'));
  } else {
    body.append(timeline(r));
  }

  // Solution photos summary (all)
  const soluzione = r.soluzione_attachments || [];
  if (soluzione.length) {
    body.append(h('div', { class: 'block-title' }, `✅ Foto delle soluzioni (${soluzione.length})`));
    body.append(photoGrid(soluzione));
  }

  // Technician action panel
  if (isTech() && !(r.status === 'chiusa')) body.append(actionPanel(r));
}

function timeline(r) {
  const created = h('div', { class: 'tl-item' },
    h('div', { class: 'tl-head' }, h('span', { class: 'tl-who' }, r.operatore || 'Operatore'),
      h('span', { class: 'tl-when' }, '· ' + fmtDateTime(r.created_at))),
    h('p', { class: 'tl-note' }, 'Richiesta aperta.'));

  const items = r.updates.map((u) => {
    const s = u.status ? statoInfo(u.status) : null;
    return h('div', { class: 'tl-item' + (s?.done ? ' done' : '') },
      h('div', { class: 'tl-head' },
        h('span', { class: 'tl-who' }, u.user_name || 'Manutentore'),
        s ? h('span', { class: 'badge', style: `background:${s.color}` }, s.label) : null,
        h('span', { class: 'tl-when' }, '· ' + fmtDateTime(u.created_at))),
      u.note ? h('p', { class: 'tl-note' }, u.note) : null,
      u.attachments?.length ? h('div', { class: 'tl-photos' }, photoGrid(u.attachments)) : null,
    );
  });
  return h('div', { class: 'timeline' }, created, ...items);
}

function photoGrid(atts) {
  if (!atts?.length) return h('div', { class: 'muted', style: 'font-size:13px' }, 'Nessuna foto.');
  return h('div', { class: 'photo-grid' },
    ...atts.map((a) => h('a', { href: '#', onclick: (e) => { e.preventDefault(); openLightbox(a.url); } },
      h('img', { src: a.url, alt: a.original_name || 'foto', loading: 'lazy' }))));
}

function openLightbox(url) {
  const ov = h('div', { class: 'modal-overlay', onclick: () => ov.remove() },
    h('img', { src: url, style: 'max-width:100%;max-height:90vh;border-radius:8px' }));
  document.body.append(ov);
}

function quickPhotoAdd(requestId, kind) {
  const up = photoUploader({ label: 'Aggiungi foto del problema' });
  const btn = h('button', { class: 'btn btn-ghost btn-sm', style: 'margin-top:8px',
    onclick: async () => {
      if (!up.files.length) return toast('Seleziona almeno una foto', 'err');
      const fd = new FormData();
      up.files.forEach((f) => fd.append('foto', f));
      btn.disabled = true;
      try { await api.addAttachments(requestId, fd); toast('Foto aggiunte', 'ok'); refreshDetail(); }
      catch (e) { toast(e.message, 'err'); btn.disabled = false; }
    } }, 'Carica foto');
  return h('div', { style: 'margin-top:10px' }, up.el, btn);
}

function actionPanel(r) {
  let chosen = '';
  const noteInput = h('textarea', { placeholder: 'Descrivi l’intervento eseguito (cosa è stato fatto, ricambi, ecc.)' });
  const up = photoUploader({ label: 'Aggiungi foto della soluzione' });

  const opts = state.meta.stati_manutentore.map((val) => {
    const s = statoInfo(val);
    const el = h('button', { type: 'button', class: 'status-opt', style: `--c:${s.color}`,
      onclick: () => {
        chosen = chosen === val ? '' : val;
        panel.querySelectorAll('.status-opt').forEach((b) => {
          const on = b.dataset.val === chosen;
          b.classList.toggle('sel', on);
          b.style.borderColor = on ? s0(b.dataset.val).color : '';
          b.style.color = on ? s0(b.dataset.val).color : '';
        });
      } },
      h('span', { class: 'dot', style: `background:${s.color}` }), s.label);
    el.dataset.val = val;
    return el;
  });
  const s0 = (v) => statoInfo(v);

  const submit = h('button', { class: 'btn btn-primary btn-block btn-lg',
    onclick: async () => {
      if (!chosen && !noteInput.value.trim() && !up.files.length) {
        return toast('Scegli uno stato, scrivi una descrizione o aggiungi una foto', 'err');
      }
      const fd = new FormData();
      if (chosen) fd.append('status', chosen);
      if (noteInput.value.trim()) fd.append('note', noteInput.value.trim());
      up.files.forEach((f) => fd.append('foto', f));
      submit.disabled = true; submit.textContent = 'Salvataggio…';
      try {
        await api.addUpdate(r.id, fd);
        toast('Aggiornamento salvato', 'ok');
        refreshDetail();
        state.refreshList?.();
      } catch (e) {
        toast(e.message, 'err');
        submit.disabled = false; submit.textContent = 'Salva aggiornamento';
      }
    } }, 'Salva aggiornamento');

  const takeBtn = !r.taken_at
    ? h('button', { class: 'btn btn-ghost btn-block', style: 'margin-bottom:12px',
        onclick: async () => {
          const fd = new FormData(); fd.append('status', 'presa_in_carico');
          takeBtn.disabled = true;
          try { await api.addUpdate(r.id, fd); toast('Richiesta presa in carico', 'ok'); refreshDetail(); state.refreshList?.(); }
          catch (e) { toast(e.message, 'err'); takeBtn.disabled = false; }
        } }, '🙋 Prendi in carico')
    : null;

  const panel = h('div', { class: 'action-card' },
    h('div', { class: 'block-title', style: 'margin-top:0' }, 'Aggiorna la richiesta'),
    takeBtn,
    h('div', { class: 'field mb0' }, h('label', {}, 'Nuovo stato'),
      h('div', { class: 'status-picker' }, ...opts)),
    h('div', { class: 'field', style: 'margin-top:14px' },
      h('label', {}, 'Descrizione intervento'), noteInput),
    h('div', { class: 'field' }, h('label', {}, 'Foto soluzione'), up.el),
    submit,
  );
  return panel;
}

// =========================================================================
// New request form
// =========================================================================
function renderNuova(c) {
  const err = h('div', { class: 'inline-error', style: 'display:none' });

  // Impianto radios (incl. "Altro")
  let impianto = '';
  const altroInput = h('input', { type: 'text', class: 'radio-altro-input', placeholder: 'Specifica impianto', style: 'display:none' });
  const impList = h('div', { class: 'radio-list' });
  const makeRadio = (val, isAltro = false) => {
    const input = h('input', { type: 'radio', name: 'impianto', value: val });
    const item = h('label', { class: 'radio-item' }, input, isAltro ? 'Altro' : val);
    input.addEventListener('change', () => {
      impianto = val;
      impList.querySelectorAll('.radio-item').forEach((x) => x.classList.remove('checked'));
      item.classList.add('checked');
      altroInput.style.display = isAltro ? 'block' : 'none';
      if (isAltro) altroInput.focus();
    });
    return item;
  };
  state.meta.impianti.forEach((i) => impList.append(makeRadio(i)));
  impList.append(makeRadio('Altro', true));
  impList.append(altroInput);

  const macchinario = h('input', { type: 'text', placeholder: 'Es. Linea 2, Impastatrice, Cella frigo…' });

  const reparto = h('select', {}, h('option', { value: '' }, 'Scegli (facoltativo)'),
    ...state.meta.reparti.map((rp) => h('option', { value: rp }, rp)));

  const descrizione = h('textarea', { placeholder: 'Cosa è successo? Descrivi il problema riscontrato.' });

  // Priorità
  let priorita = 'verde';
  const prioWrap = h('div', { class: 'priority-select' });
  state.meta.priorita.forEach((p) => {
    const input = h('input', { type: 'radio', name: 'prio', value: p.value, checked: p.value === 'verde' });
    const opt = h('label', { class: 'priority-opt' + (p.value === 'verde' ? ' checked' : ''), style: `color:${p.color}` },
      input, h('span', { class: 'pdot', style: `background:${p.color}` }), h('span', { style: 'color:var(--ink)' }, p.label));
    input.addEventListener('change', () => {
      priorita = p.value;
      prioWrap.querySelectorAll('.priority-opt').forEach((x) => x.classList.remove('checked'));
      opt.classList.add('checked');
    });
    prioWrap.append(opt);
  });

  const note = h('textarea', { placeholder: 'Note aggiuntive (facoltativo)' });
  const operatore = h('input', { type: 'text', value: state.user.full_name, placeholder: 'Il tuo nome' });
  const up = photoUploader({ label: 'Aggiungi foto del problema' });

  const submit = h('button', { type: 'submit', class: 'btn btn-primary btn-lg btn-block' }, 'Invia richiesta');

  const form = h('form', {
    onsubmit: async (e) => {
      e.preventDefault();
      err.style.display = 'none';
      if (!impianto) return showErr("Scegli l'impianto");
      if (impianto === 'Altro' && !altroInput.value.trim()) return showErr("Specifica l'impianto (Altro)");
      if (!macchinario.value.trim()) return showErr("Inserisci l'impianto o macchinario in questione");
      if (!operatore.value.trim()) return showErr('Il campo Operatore è obbligatorio');

      const fd = new FormData();
      fd.append('impianto', impianto);
      if (impianto === 'Altro') fd.append('impianto_altro', altroInput.value.trim());
      fd.append('macchinario', macchinario.value.trim());
      fd.append('reparto', reparto.value);
      fd.append('descrizione', descrizione.value.trim());
      fd.append('priorita', priorita);
      fd.append('note', note.value.trim());
      fd.append('operatore', operatore.value.trim());
      up.files.forEach((f) => fd.append('foto', f));

      submit.disabled = true; submit.textContent = 'Invio…';
      try {
        await api.createRequest(fd);
        toast('Richiesta inviata con successo', 'ok');
        setView('richieste');
      } catch (ex) {
        showErr(ex.message);
        submit.disabled = false; submit.textContent = 'Invia richiesta';
      }
    },
  },
    field('Scegli l’impianto', impList, true),
    field('Inserisci l’impianto o macchinario in questione', macchinario, true),
    field('Reparto', reparto),
    field('Descrizione evento', descrizione),
    field('Livello Priorità', prioWrap),
    field('Note', note),
    field('Operatore', operatore, true),
    field('Foto del problema', up.el),
    err,
    submit,
  );

  function showErr(m) { err.textContent = m; err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }

  c.append(h('div', { class: 'card form-card' },
    h('h2', { style: 'margin-top:0' }, 'Nuova richiesta di manutenzione'),
    h('p', { class: 'muted', style: 'margin-top:-4px' }, 'Compila il modulo. I campi con * sono obbligatori.'),
    form));
}

function field(label, control, required = false) {
  return h('div', { class: 'field' },
    h('label', {}, label, required ? h('span', { class: 'req' }, ' *') : null),
    control);
}

// =========================================================================
// Photo uploader component
// =========================================================================
function photoUploader({ label = 'Aggiungi foto' } = {}) {
  const files = [];
  const thumbs = h('div', { class: 'thumbs' });
  const input = h('input', { type: 'file', accept: 'image/*', multiple: true, style: 'display:none' });

  input.addEventListener('change', async () => {
    const picked = Array.from(input.files || []);
    input.value = '';
    for (const f of picked) {
      const c = await compressImage(f);
      files.push(c);
      addThumb(c);
    }
  });

  function addThumb(file) {
    const url = URL.createObjectURL(file);
    const t = h('div', { class: 'thumb' },
      h('img', { src: url }),
      h('button', { type: 'button', class: 'rm', onclick: () => {
        const i = files.indexOf(file); if (i >= 0) files.splice(i, 1);
        t.remove(); URL.revokeObjectURL(url);
      } }, '×'));
    thumbs.append(t);
  }

  const el = h('div', { class: 'uploader' },
    h('div', {}, '📷 ', h('label', {}, label, input)),
    h('div', { class: 'hint' }, 'Scatta una foto o scegli dalla galleria'),
    thumbs);

  return { el, files, reset() { files.length = 0; clear(thumbs); } };
}

// =========================================================================
// Users (admin)
// =========================================================================
async function renderUtenti(c) {
  c.append(h('div', { style: 'display:flex;align-items:center;gap:12px;margin-bottom:16px' },
    h('h2', { style: 'margin:0;flex:1' }, 'Gestione utenti'),
    h('button', { class: 'btn btn-primary', onclick: () => openUserModal() }, '➕ Nuovo utente')));
  const wrap = h('div', { class: 'table-wrap' });
  c.append(wrap);

  async function load() {
    try {
      const { users } = await api.listUsers();
      const rows = users.map((u) => h('tr', {},
        h('td', {}, h('strong', {}, u.full_name)),
        h('td', {}, u.username),
        h('td', {}, h('span', { class: 'role-badge' }, u.role)),
        h('td', {}, u.active ? h('span', { class: 'pill-on' }, 'Attivo') : h('span', { class: 'pill-off' }, 'Disattivato')),
        h('td', { class: 'nowrap' },
          h('button', { class: 'btn btn-ghost btn-sm', onclick: () => openUserModal(u) }, 'Modifica'),
          ' ',
          u.id !== state.user.id
            ? h('button', { class: 'btn btn-ghost btn-sm', onclick: async () => {
                try { await api.updateUser(u.id, { active: u.active ? 0 : 1 }); load(); }
                catch (e) { toast(e.message, 'err'); }
              } }, u.active ? 'Disattiva' : 'Riattiva')
            : null),
      ));
      clear(wrap).append(h('table', { class: 'users' },
        h('thead', {}, h('tr', {},
          h('th', {}, 'Nome'), h('th', {}, 'Username'), h('th', {}, 'Ruolo'), h('th', {}, 'Stato'), h('th', {}, 'Azioni'))),
        h('tbody', {}, ...rows)));
    } catch (e) { toast(e.message, 'err'); }
  }

  function openUserModal(user) {
    const editing = !!user;
    const fullName = h('input', { type: 'text', value: user?.full_name || '', placeholder: 'Nome e cognome' });
    const username = h('input', { type: 'text', value: user?.username || '', placeholder: 'Username', disabled: editing });
    const role = h('select', {}, ...['operatore', 'manutentore', 'admin'].map((rr) =>
      h('option', { value: rr, selected: user?.role === rr }, rr)));
    const password = h('input', { type: 'password', placeholder: editing ? 'Lascia vuoto per non cambiare' : 'Password (min 6 caratteri)' });
    const err = h('div', { class: 'inline-error', style: 'display:none' });

    const { close } = openModal(editing ? 'Modifica utente' : 'Nuovo utente',
      h('div', {},
        field('Nome e cognome', fullName, true),
        field('Username', username, true),
        field('Ruolo', role, true),
        field('Password', password, !editing),
        err),
      async () => {
        err.style.display = 'none';
        try {
          if (editing) {
            const payload = { full_name: fullName.value.trim(), role: role.value };
            if (password.value) payload.password = password.value;
            await api.updateUser(user.id, payload);
          } else {
            await api.createUser({
              full_name: fullName.value.trim(), username: username.value.trim(),
              role: role.value, password: password.value,
            });
          }
          close(); load(); toast('Utente salvato', 'ok');
        } catch (e) { err.textContent = e.message; err.style.display = 'block'; }
      });
  }

  load();
}

// =========================================================================
// Modals
// =========================================================================
function openModal(title, bodyNode, onConfirm, confirmLabel = 'Salva') {
  const overlay = h('div', { class: 'modal-overlay', onclick: (e) => { if (e.target === overlay) close(); } });
  const confirmBtn = h('button', { class: 'btn btn-primary', onclick: async () => {
    confirmBtn.disabled = true;
    try { await onConfirm(); } finally { confirmBtn.disabled = false; }
  } }, confirmLabel);
  const modal = h('div', { class: 'modal' },
    h('h3', {}, title), bodyNode,
    h('div', { class: 'modal-actions' },
      h('button', { class: 'btn btn-ghost', onclick: () => close() }, 'Annulla'), confirmBtn));
  overlay.append(modal);
  document.body.append(overlay);
  function close() { overlay.remove(); }
  return { close };
}

function openChangePassword() {
  const cur = h('input', { type: 'password', placeholder: 'Password attuale' });
  const nw = h('input', { type: 'password', placeholder: 'Nuova password (min 6 caratteri)' });
  const err = h('div', { class: 'inline-error', style: 'display:none' });
  const { close } = openModal('Cambia password',
    h('div', {}, field('Password attuale', cur, true), field('Nuova password', nw, true), err),
    async () => {
      err.style.display = 'none';
      try { await api.changePassword(cur.value, nw.value); close(); toast('Password aggiornata', 'ok'); }
      catch (e) { err.textContent = e.message; err.style.display = 'block'; }
    });
}

// =========================================================================
// Utilities
// =========================================================================
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// Fallback polling (every 20s) in case SSE isn't delivered.
let pollTimer = null;
function startPolling() {
  clearInterval(pollTimer);
  pollTimer = setInterval(() => {
    if (state.view === 'richieste' && document.visibilityState === 'visible') state.refreshList?.();
  }, 20000);
}
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') { state.refreshList?.(); if (state.detailId) refreshDetail(); }
});

init();
