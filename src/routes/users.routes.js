import express from 'express';
import db from '../db.js';
import { hashPassword, requireRole } from '../auth.js';
import { RUOLI } from '../lib/constants.js';

const router = express.Router();

// All user-management endpoints are admin-only.
router.use(requireRole('admin'));

router.get('/', (_req, res) => {
  const users = db
    .prepare(
      'SELECT id, username, full_name, role, active, created_at FROM users ORDER BY role, username'
    )
    .all();
  res.json({ users });
});

router.post('/', (req, res) => {
  const { username, password, full_name, role } = req.body || {};
  if (!username || !password || !full_name || !role) {
    return res.status(400).json({ error: 'Tutti i campi sono obbligatori' });
  }
  if (!RUOLI.includes(role)) {
    return res.status(400).json({ error: 'Ruolo non valido' });
  }
  if (String(password).length < 6) {
    return res.status(400).json({ error: 'La password deve avere almeno 6 caratteri' });
  }
  const exists = db
    .prepare('SELECT id FROM users WHERE username = ? COLLATE NOCASE')
    .get(String(username).trim());
  if (exists) {
    return res.status(409).json({ error: 'Username già esistente' });
  }
  const info = db
    .prepare(
      'INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
    )
    .run(String(username).trim(), hashPassword(password), String(full_name).trim(), role);
  const user = db
    .prepare('SELECT id, username, full_name, role, active, created_at FROM users WHERE id = ?')
    .get(info.lastInsertRowid);
  res.status(201).json({ user });
});

router.patch('/:id', (req, res) => {
  const id = Number(req.params.id);
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
  if (!user) return res.status(404).json({ error: 'Utente non trovato' });

  const { full_name, role, active, password } = req.body || {};

  if (role !== undefined && !RUOLI.includes(role)) {
    return res.status(400).json({ error: 'Ruolo non valido' });
  }
  // Prevent an admin from locking themselves out / demoting the last admin.
  if (req.user.id === id && (active === 0 || (role && role !== 'admin'))) {
    return res
      .status(400)
      .json({ error: 'Non puoi disattivare o cambiare il ruolo del tuo stesso account' });
  }

  const fields = [];
  const values = [];
  if (full_name !== undefined) {
    fields.push('full_name = ?');
    values.push(String(full_name).trim());
  }
  if (role !== undefined) {
    fields.push('role = ?');
    values.push(role);
  }
  if (active !== undefined) {
    fields.push('active = ?');
    values.push(active ? 1 : 0);
  }
  if (password) {
    if (String(password).length < 6) {
      return res.status(400).json({ error: 'La password deve avere almeno 6 caratteri' });
    }
    fields.push('password_hash = ?');
    values.push(hashPassword(password));
  }
  if (!fields.length) return res.status(400).json({ error: 'Nessuna modifica fornita' });

  values.push(id);
  db.prepare(`UPDATE users SET ${fields.join(', ')} WHERE id = ?`).run(...values);
  const updated = db
    .prepare('SELECT id, username, full_name, role, active, created_at FROM users WHERE id = ?')
    .get(id);
  res.json({ user: updated });
});

export default router;
