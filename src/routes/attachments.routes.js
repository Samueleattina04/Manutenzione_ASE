import express from 'express';
import path from 'node:path';
import fs from 'node:fs';
import db from '../db.js';
import { requireAuth, requireRole } from '../auth.js';
import { UPLOAD_DIR } from '../lib/uploads.js';
import { broadcast } from '../lib/events.js';

const router = express.Router();
router.use(requireAuth);

// Serve / download an image file (only to authenticated users).
router.get('/:id', (req, res) => {
  const att = db.prepare('SELECT * FROM attachments WHERE id = ?').get(Number(req.params.id));
  if (!att) return res.status(404).json({ error: 'Allegato non trovato' });

  const filePath = path.join(UPLOAD_DIR, att.filename);
  if (!filePath.startsWith(UPLOAD_DIR) || !fs.existsSync(filePath)) {
    return res.status(404).json({ error: 'File non trovato' });
  }
  if (att.mime_type) res.type(att.mime_type);
  if (req.query.download === '1' && att.original_name) {
    res.setHeader('Content-Disposition', `attachment; filename="${att.original_name}"`);
  }
  res.setHeader('Cache-Control', 'private, max-age=86400');
  fs.createReadStream(filePath).pipe(res);
});

// Delete an attachment (admin, or the manutentore/uploader).
router.delete('/:id', requireRole('manutentore', 'admin'), (req, res) => {
  const att = db.prepare('SELECT * FROM attachments WHERE id = ?').get(Number(req.params.id));
  if (!att) return res.status(404).json({ error: 'Allegato non trovato' });
  if (req.user.role !== 'admin' && att.uploaded_by !== req.user.id) {
    return res.status(403).json({ error: 'Puoi eliminare solo gli allegati che hai caricato' });
  }
  const filePath = path.join(UPLOAD_DIR, att.filename);
  db.prepare('DELETE FROM attachments WHERE id = ?').run(att.id);
  if (filePath.startsWith(UPLOAD_DIR) && fs.existsSync(filePath)) {
    try {
      fs.unlinkSync(filePath);
    } catch {
      /* ignore */
    }
  }
  broadcast('request:update', { id: att.request_id });
  res.json({ ok: true });
});

export default router;
