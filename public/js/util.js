// Small DOM + formatting helpers (no framework).

export function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) {
    if (v == null || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k === 'html') el.innerHTML = v;
    else if (k === 'dataset') Object.assign(el.dataset, v);
    else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
    else if (k in el && k !== 'list') {
      try { el[k] = v; } catch { el.setAttribute(k, v); }
    } else el.setAttribute(k, v);
  }
  for (const c of children.flat()) {
    if (c == null || c === false) continue;
    el.append(c.nodeType ? c : document.createTextNode(String(c)));
  }
  return el;
}

export function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
  );
}

export function clear(node) {
  while (node.firstChild) node.removeChild(node.firstChild);
  return node;
}

// SQLite stores UTC datetimes ("YYYY-MM-DD HH:MM:SS"). Parse as UTC.
function parseDate(s) {
  if (!s) return null;
  if (s instanceof Date) return s;
  const iso = s.includes('T') ? s : s.replace(' ', 'T') + 'Z';
  const d = new Date(iso);
  return isNaN(d) ? null : d;
}

export function fmtDateTime(s) {
  const d = parseDate(s);
  if (!d) return '—';
  return d.toLocaleString('it-IT', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}

export function fmtRelative(s) {
  const d = parseDate(s);
  if (!d) return '';
  const diff = (Date.now() - d.getTime()) / 1000;
  const abs = Math.abs(diff);
  if (abs < 60) return 'adesso';
  if (abs < 3600) return `${Math.floor(abs / 60)} min fa`;
  if (abs < 86400) return `${Math.floor(abs / 3600)} h fa`;
  const days = Math.floor(abs / 86400);
  if (days < 30) return `${days} g fa`;
  return fmtDateTime(s);
}

// Difference between two datetimes, human readable (for resolution time).
export function fmtDuration(from, to) {
  const a = parseDate(from), b = parseDate(to);
  if (!a || !b) return '';
  let sec = Math.max(0, (b.getTime() - a.getTime()) / 1000);
  const d = Math.floor(sec / 86400); sec -= d * 86400;
  const hh = Math.floor(sec / 3600); sec -= hh * 3600;
  const mm = Math.floor(sec / 60);
  const parts = [];
  if (d) parts.push(`${d}g`);
  if (hh) parts.push(`${hh}h`);
  if (mm && !d) parts.push(`${mm}min`);
  return parts.join(' ') || '<1min';
}

export function initials(name) {
  return String(name || '?')
    .trim().split(/\s+/).slice(0, 2).map((w) => w[0]?.toUpperCase() || '').join('') || '?';
}

// ---- Toasts ----
export function toast(message, kind = '') {
  const box = document.getElementById('toasts');
  const t = h('div', { class: `toast ${kind}` }, message);
  box.appendChild(t);
  setTimeout(() => {
    t.style.transition = 'opacity .3s';
    t.style.opacity = '0';
    setTimeout(() => t.remove(), 300);
  }, kind === 'err' ? 5000 : 3200);
}

// ---- Client-side image compression (phone photos can be huge) ----
export async function compressImage(file, maxSize = 1600, quality = 0.82) {
  if (!file.type.startsWith('image/')) return file;
  // HEIC and friends can't be decoded by canvas in all browsers -> send as-is.
  const decodable = /image\/(jpeg|png|webp|gif)/.test(file.type);
  if (!decodable) return file;
  try {
    const bitmap = await createImageBitmap(file);
    let { width, height } = bitmap;
    if (width <= maxSize && height <= maxSize && file.size < 900 * 1024) {
      bitmap.close?.();
      return file;
    }
    const scale = Math.min(1, maxSize / Math.max(width, height));
    width = Math.round(width * scale);
    height = Math.round(height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = width; canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(bitmap, 0, 0, width, height);
    bitmap.close?.();
    const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', quality));
    if (!blob || blob.size >= file.size) return file;
    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg' });
  } catch {
    return file;
  }
}
