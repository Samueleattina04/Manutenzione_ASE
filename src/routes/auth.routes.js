import express from 'express';
import db from '../db.js';
import {
  verifyPassword,
  hashPassword,
  signToken,
  setAuthCookie,
  clearAuthCookie,
  requireAuth,
} from '../auth.js';

const router = express.Router();

router.post('/login', (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) {
    return res.status(400).json({ error: 'Username e password sono obbligatori' });
  }
  const user = db
    .prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE')
    .get(String(username).trim());

  if (!user || !user.active || !verifyPassword(password, user.password_hash)) {
    return res.status(401).json({ error: 'Credenziali non valide' });
  }

  const token = signToken(user);
  setAuthCookie(res, token);
  res.json({
    user: { id: user.id, username: user.username, full_name: user.full_name, role: user.role },
  });
});

router.post('/logout', (_req, res) => {
  clearAuthCookie(res);
  res.json({ ok: true });
});

router.get('/me', (req, res) => {
  if (!req.user) return res.json({ user: null });
  const { id, username, full_name, role } = req.user;
  res.json({ user: { id, username, full_name, role } });
});

router.post('/change-password', requireAuth, (req, res) => {
  const { currentPassword, newPassword } = req.body || {};
  if (!newPassword || String(newPassword).length < 6) {
    return res.status(400).json({ error: 'La nuova password deve avere almeno 6 caratteri' });
  }
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(req.user.id);
  if (!verifyPassword(currentPassword || '', user.password_hash)) {
    return res.status(400).json({ error: 'Password attuale non corretta' });
  }
  db.prepare('UPDATE users SET password_hash = ? WHERE id = ?').run(
    hashPassword(newPassword),
    req.user.id
  );
  res.json({ ok: true });
});

export default router;
