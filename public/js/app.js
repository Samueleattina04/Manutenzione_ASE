// Manutenzione ASE — progressive enhancement (no build step required).
(function () {
  'use strict';

  // ---- User menu ----------------------------------------------------------
  const menuBtn = document.querySelector('[data-usermenu-btn]');
  const menuPanel = document.querySelector('[data-usermenu-panel]');
  if (menuBtn && menuPanel) {
    menuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      menuPanel.hidden = !menuPanel.hidden;
    });
    document.addEventListener('click', () => { menuPanel.hidden = true; });
  }

  // ---- Radio groups styling (.radio-item / .priority-opt / .status-opt) ----
  function wireRadioGroup(selectorItem, selectorGroup, checkedClass) {
    document.querySelectorAll(selectorItem).forEach((item) => {
      const input = item.querySelector('input[type=radio]');
      if (!input) return;
      const sync = () => {
        const group = item.closest(selectorGroup) || document;
        group.querySelectorAll(selectorItem).forEach((x) => x.classList.remove(checkedClass));
        if (input.checked) item.classList.add(checkedClass);
      };
      input.addEventListener('change', () => {
        const group = item.closest(selectorGroup) || document;
        group.querySelectorAll('input[type=radio]').forEach((r) => {
          const li = r.closest(selectorItem);
          if (li) li.classList.remove(checkedClass);
        });
        sync();
        handleAltro();
      });
      if (input.checked) item.classList.add(checkedClass);
    });
  }
  wireRadioGroup('.radio-item', '.radio-list', 'checked');
  wireRadioGroup('.priority-opt', '.priority-select', 'checked');
  wireRadioGroup('.status-opt', '.status-picker', 'sel');

  // ---- "Altro" impianto reveal --------------------------------------------
  const impSelect = document.querySelector('select[name=impianto]');
  function handleAltro(focus = false) {
    const altro = document.querySelector('[data-altro-input]');
    if (!altro) return;
    const checked = document.querySelector('input[name=impianto]:checked');
    const val = impSelect ? impSelect.value : (checked ? checked.value : '');
    const show = val === 'Altro';
    altro.style.display = show ? 'block' : 'none';
    const inp = altro.querySelector('input');
    if (inp) { inp.required = !!show; if (show && focus) inp.focus(); }
  }
  if (impSelect) impSelect.addEventListener('change', () => handleAltro(true));
  handleAltro();

  // ---- Image compression + preview for file inputs ------------------------
  async function compressImage(file, maxSize, quality) {
    if (!file.type.startsWith('image/')) return file;
    if (!/image\/(jpeg|png|webp|gif)/.test(file.type)) return file; // HEIC etc: leave as-is
    try {
      const bitmap = await createImageBitmap(file);
      let { width, height } = bitmap;
      if (width <= maxSize && height <= maxSize && file.size < 900 * 1024) { bitmap.close && bitmap.close(); return file; }
      const scale = Math.min(1, maxSize / Math.max(width, height));
      width = Math.round(width * scale); height = Math.round(height * scale);
      const canvas = document.createElement('canvas');
      canvas.width = width; canvas.height = height;
      canvas.getContext('2d').drawImage(bitmap, 0, 0, width, height);
      bitmap.close && bitmap.close();
      const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', quality));
      if (!blob || blob.size >= file.size) return file;
      return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch { return file; }
  }

  document.querySelectorAll('[data-uploader]').forEach((box) => {
    const pickers = box.querySelectorAll('input[data-picker]');
    const carrier = box.querySelector('input[data-carrier]');
    const thumbs = box.querySelector('[data-thumbs]');
    if (!pickers.length || !carrier || !thumbs) return;
    const files = [];

    pickers.forEach((picker) => {
      picker.addEventListener('change', async () => {
        const picked = Array.from(picker.files || []);
        picker.value = ''; // consenti di riscattare/riscegliere lo stesso file
        for (const f of picked) {
          const c = await compressImage(f, 1600, 0.82);
          files.push(c);
          addThumb(c);
        }
        syncCarrier();
      });
    });

    function addThumb(file) {
      const url = URL.createObjectURL(file);
      const t = document.createElement('div');
      t.className = 'thumb';
      const img = document.createElement('img'); img.src = url;
      const rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'rm'; rm.textContent = '×';
      rm.addEventListener('click', () => {
        const i = files.indexOf(file); if (i >= 0) files.splice(i, 1);
        t.remove(); URL.revokeObjectURL(url); syncCarrier();
      });
      t.append(img, rm); thumbs.append(t);
    }

    function syncCarrier() {
      const dt = new DataTransfer();
      files.forEach((f) => dt.items.add(f));
      carrier.files = dt.files;
    }
  });

  // ---- Lightbox -----------------------------------------------------------
  document.addEventListener('click', (e) => {
    const a = e.target.closest('[data-lightbox]');
    if (!a) return;
    e.preventDefault();
    const ov = document.createElement('div');
    ov.className = 'modal-overlay';
    ov.innerHTML = '<img style="max-width:100%;max-height:90vh;border-radius:8px" src="' + a.getAttribute('href') + '">';
    ov.addEventListener('click', () => ov.remove());
    document.body.append(ov);
  });

  // ---- Modals (data-modal-open / data-modal) ------------------------------
  document.querySelectorAll('[data-modal-open]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const m = document.querySelector('#' + btn.getAttribute('data-modal-open'));
      if (m) m.hidden = false;
    });
  });
  document.querySelectorAll('[data-modal]').forEach((m) => {
    m.addEventListener('click', (e) => { if (e.target === m || e.target.closest('[data-modal-close]')) m.hidden = true; });
  });

  // ---- Live polling of a fragment -----------------------------------------
  function startPolling(el, buildUrl, intervalMs) {
    let busy = false;
    async function tick() {
      if (busy || document.visibilityState !== 'visible') return;
      busy = true;
      try {
        const res = await fetch(buildUrl(), { headers: { 'X-Requested-With': 'fetch' } });
        if (res.ok) {
          const html = await res.text();
          // Only swap when changed, to avoid clobbering focus/scroll needlessly.
          if (html && html !== el.dataset.lastHtml) {
            el.innerHTML = html;
            el.dataset.lastHtml = html;
          }
        }
      } catch { /* ignore transient errors */ }
      busy = false;
    }
    setInterval(tick, intervalMs);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') tick(); });
  }

  const lista = document.querySelector('[data-poll="lista"]');
  if (lista) {
    startPolling(lista, () => lista.dataset.pollUrl + (window.location.search || ''), 15000);
  }
  const dettaglio = document.querySelector('[data-poll="dettaglio"]');
  if (dettaglio) {
    startPolling(dettaglio, () => dettaglio.dataset.pollUrl, 15000);
  }

  // ---- Confirm before submit (es. eliminazione) ---------------------------
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });

  // ---- Submit guard (avoid double submit) ---------------------------------
  document.querySelectorAll('form[data-guard]').forEach((form) => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Attendere…'; }
      // Re-enable after a while in case validation blocks navigation client-side.
      setTimeout(() => { if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || btn.textContent; } }, 8000);
    });
  });
})();
