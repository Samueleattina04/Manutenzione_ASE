import express from 'express';
import fs from 'node:fs';
import path from 'node:path';
import db from '../db.js';
import { requireAuth, requireRole } from '../auth.js';
import { upload, UPLOAD_DIR } from '../lib/uploads.js';
import { broadcast } from '../lib/events.js';
import {
  IMPIANTI,
  REPARTI,
  isValidPriorita,
  STATI_MANUTENTORE,
  statoIsDone,
} from '../lib/constants.js';

const router = express.Router();
router.use(requireAuth);

// ---- helpers ---------------------------------------------------------------

// Remove files multer already wrote to disk when a request is later rejected.
function discardUploads(req) {
  for (const f of req.files || []) {
    const p = path.join(UPLOAD_DIR, f.filename);
    if (p.startsWith(UPLOAD_DIR)) {
      try { fs.unlinkSync(p); } catch { /* ignore */ }
    }
  }
}

// Reject with a JSON error after cleaning up any uploaded files.
function reject(req, res, status, error) {
  discardUploads(req);
  return res.status(status).json({ error });
}

function attachmentsFor(requestId) {
  return db
    .prepare(
      `SELECT a.id, a.kind, a.original_name, a.mime_type, a.size, a.created_at, a.update_id,
              u.full_name AS uploaded_by_name
         FROM attachments a
         LEFT JOIN users u ON u.id = a.uploaded_by
        WHERE a.request_id = ?
        ORDER BY a.created_at ASC, a.id ASC`
    )
    .all(requestId)
    .map((a) => ({ ...a, url: `/api/attachments/${a.id}` }));
}

function serializeDetail(id) {
  const request = db
    .prepare(
      `SELECT r.*, cu.full_name AS created_by_name, au.full_name AS assigned_to_name
         FROM requests r
         LEFT JOIN users cu ON cu.id = r.created_by
         LEFT JOIN users au ON au.id = r.assigned_to
        WHERE r.id = ?`
    )
    .get(id);
  if (!request) return null;

  const updates = db
    .prepare(
      `SELECT up.id, up.status, up.note, up.created_at, up.user_id, u.full_name AS user_name
         FROM updates up
         LEFT JOIN users u ON u.id = up.user_id
        WHERE up.request_id = ?
        ORDER BY up.created_at ASC, up.id ASC`
    )
    .all(id);

  const allAttachments = attachmentsFor(id);
  for (const up of updates) {
    up.attachments = allAttachments.filter((a) => a.update_id === up.id);
  }

  return {
    ...request,
    attachments: allAttachments,
    problema_attachments: allAttachments.filter((a) => a.kind === 'problema'),
    soluzione_attachments: allAttachments.filter((a) => a.kind === 'soluzione'),
    updates,
  };
}

// ---- list ------------------------------------------------------------------

router.get('/', (req, res) => {
  const { status, priorita, impianto, mine, q } = req.query;
  const where = [];
  const params = [];

  // "attive" = not yet resolved/closed, "chiuse" = done.
  if (status === 'attive') {
    where.push("r.status NOT IN ('risolta','chiusa')");
  } else if (status === 'chiuse') {
    where.push("r.status IN ('risolta','chiusa')");
  } else if (status && status !== 'tutte') {
    where.push('r.status = ?');
    params.push(status);
  }
  if (priorita && isValidPriorita(priorita)) {
    where.push('r.priorita = ?');
    params.push(priorita);
  }
  if (impianto) {
    where.push('r.impianto = ?');
    params.push(impianto);
  }
  if (mine === '1') {
    where.push('r.created_by = ?');
    params.push(req.user.id);
  }
  if (q) {
    where.push(
      '(r.macchinario LIKE ? OR r.descrizione LIKE ? OR r.reparto LIKE ? OR r.operatore LIKE ? OR r.note LIKE ?)'
    );
    const like = `%${q}%`;
    params.push(like, like, like, like, like);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const rows = db
    .prepare(
      `SELECT r.id, r.impianto, r.impianto_altro, r.macchinario, r.reparto, r.descrizione,
              r.priorita, r.status, r.operatore, r.created_at, r.updated_at, r.taken_at,
              r.resolved_at, r.created_by,
              au.full_name AS assigned_to_name,
              (SELECT COUNT(*) FROM attachments a WHERE a.request_id = r.id) AS n_attachments
         FROM requests r
         LEFT JOIN users au ON au.id = r.assigned_to
         ${whereSql}
        ORDER BY
          CASE r.status WHEN 'risolta' THEN 2 WHEN 'chiusa' THEN 2 ELSE 1 END ASC,
          CASE r.priorita WHEN 'rosso' THEN 3 WHEN 'giallo' THEN 2 ELSE 1 END DESC,
          r.created_at DESC`
    )
    .all(...params);

  res.json({ requests: rows });
});

// ---- counters for the dashboard -------------------------------------------

router.get('/stats', (req, res) => {
  const byStatus = db
    .prepare('SELECT status, COUNT(*) AS n FROM requests GROUP BY status')
    .all();
  const attive = db
    .prepare("SELECT COUNT(*) AS n FROM requests WHERE status NOT IN ('risolta','chiusa')")
    .get().n;
  const urgenti = db
    .prepare(
      "SELECT COUNT(*) AS n FROM requests WHERE priorita = 'rosso' AND status NOT IN ('risolta','chiusa')"
    )
    .get().n;
  const mie = db
    .prepare('SELECT COUNT(*) AS n FROM requests WHERE created_by = ?')
    .get(req.user.id).n;
  res.json({ byStatus, attive, urgenti, mie });
});

// ---- detail ----------------------------------------------------------------

router.get('/:id', (req, res) => {
  const detail = serializeDetail(Number(req.params.id));
  if (!detail) return res.status(404).json({ error: 'Richiesta non trovata' });
  res.json({ request: detail });
});

// ---- create (operatore / manutentore / admin) ------------------------------

router.post('/', upload.array('foto', 8), (req, res) => {
  const b = req.body || {};
  const impianto = (b.impianto || '').trim();
  const macchinario = (b.macchinario || '').trim();
  const operatore = (b.operatore || '').trim() || req.user.full_name;
  const priorita = (b.priorita || 'verde').trim();

  if (!impianto) return reject(req, res, 400, "Scegli l'impianto");
  if (impianto === 'Altro' && !(b.impianto_altro || '').trim()) {
    return reject(req, res, 400, "Specifica l'impianto (campo Altro)");
  }
  if (impianto !== 'Altro' && !IMPIANTI.includes(impianto)) {
    return reject(req, res, 400, 'Impianto non valido');
  }
  if (!macchinario) {
    return reject(req, res, 400, "Inserisci l'impianto o macchinario in questione");
  }
  if (!operatore) return reject(req, res, 400, 'Il campo Operatore è obbligatorio');
  if (!isValidPriorita(priorita)) return reject(req, res, 400, 'Priorità non valida');
  const reparto = (b.reparto || '').trim();
  if (reparto && !REPARTI.includes(reparto)) {
    return reject(req, res, 400, 'Reparto non valido');
  }

  const insert = db.prepare(
    `INSERT INTO requests
       (impianto, impianto_altro, macchinario, reparto, descrizione, priorita, note, operatore, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`
  );

  const tx = db.transaction(() => {
    const info = insert.run(
      impianto,
      impianto === 'Altro' ? (b.impianto_altro || '').trim() : null,
      macchinario,
      reparto || null,
      (b.descrizione || '').trim() || null,
      priorita,
      (b.note || '').trim() || null,
      operatore,
      req.user.id
    );
    const requestId = info.lastInsertRowid;

    const attStmt = db.prepare(
      `INSERT INTO attachments (request_id, kind, filename, original_name, mime_type, size, uploaded_by)
       VALUES (?, 'problema', ?, ?, ?, ?, ?)`
    );
    for (const f of req.files || []) {
      attStmt.run(requestId, f.filename, f.originalname, f.mimetype, f.size, req.user.id);
    }
    return requestId;
  });

  const requestId = tx();
  const detail = serializeDetail(requestId);
  broadcast('request:new', { id: requestId, priorita, impianto });
  res.status(201).json({ request: detail });
});

// ---- status update / intervention (manutentore / admin) --------------------

router.post(
  '/:id/updates',
  requireRole('manutentore', 'admin'),
  upload.array('foto', 8),
  (req, res) => {
    const id = Number(req.params.id);
    const request = db.prepare('SELECT * FROM requests WHERE id = ?').get(id);
    if (!request) return reject(req, res, 404, 'Richiesta non trovata');

    const b = req.body || {};
    const note = (b.note || '').trim();
    const status = (b.status || '').trim();
    const hasFiles = (req.files || []).length > 0;

    if (status && !STATI_MANUTENTORE.includes(status)) {
      return reject(req, res, 400, 'Stato non valido');
    }
    if (!status && !note && !hasFiles) {
      return reject(req, res, 400, 'Inserisci un aggiornamento: stato, descrizione o foto');
    }

    const tx = db.transaction(() => {
      const upInfo = db
        .prepare(
          'INSERT INTO updates (request_id, user_id, status, note) VALUES (?, ?, ?, ?)'
        )
        .run(id, req.user.id, status || null, note || null);
      const updateId = upInfo.lastInsertRowid;

      // Apply state transition to the request itself.
      const fields = ['updated_at = datetime(\'now\')'];
      const values = [];

      if (status) {
        fields.push('status = ?');
        values.push(status);

        // First technician to act takes charge of the request.
        if (!request.assigned_to) {
          fields.push('assigned_to = ?');
          values.push(req.user.id);
        }
        if (!request.taken_at) {
          fields.push("taken_at = datetime('now')");
        }
        if (statoIsDone(status)) {
          fields.push("resolved_at = datetime('now')");
        } else {
          fields.push('resolved_at = NULL');
        }
      }

      values.push(id);
      db.prepare(`UPDATE requests SET ${fields.join(', ')} WHERE id = ?`).run(...values);

      const attStmt = db.prepare(
        `INSERT INTO attachments (request_id, update_id, kind, filename, original_name, mime_type, size, uploaded_by)
         VALUES (?, ?, 'soluzione', ?, ?, ?, ?, ?)`
      );
      for (const f of req.files || []) {
        attStmt.run(id, updateId, f.filename, f.originalname, f.mimetype, f.size, req.user.id);
      }
    });

    tx();
    const detail = serializeDetail(id);
    broadcast('request:update', { id, status: status || request.status });
    res.status(201).json({ request: detail });
  }
);

// ---- add photos to an existing request -------------------------------------

router.post('/:id/attachments', upload.array('foto', 8), (req, res) => {
  const id = Number(req.params.id);
  const request = db.prepare('SELECT * FROM requests WHERE id = ?').get(id);
  if (!request) return reject(req, res, 404, 'Richiesta non trovata');
  if (!(req.files || []).length) {
    return res.status(400).json({ error: 'Nessuna foto caricata' });
  }
  const kind = req.user.role === 'manutentore' || req.user.role === 'admin' ? 'soluzione' : 'problema';
  const attStmt = db.prepare(
    `INSERT INTO attachments (request_id, kind, filename, original_name, mime_type, size, uploaded_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)`
  );
  const tx = db.transaction(() => {
    for (const f of req.files) {
      attStmt.run(id, kind, f.filename, f.originalname, f.mimetype, f.size, req.user.id);
    }
    db.prepare("UPDATE requests SET updated_at = datetime('now') WHERE id = ?").run(id);
  });
  tx();
  broadcast('request:update', { id });
  res.status(201).json({ request: serializeDetail(id) });
});

export default router;
