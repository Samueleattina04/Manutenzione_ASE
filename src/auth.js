import jwt from 'jsonwebtoken';
import bcrypt from 'bcryptjs';
import db from './db.js';

const JWT_SECRET =
  process.env.JWT_SECRET ||
  'cambia-questo-segreto-in-produzione-manutenzione-ase-2025';
const TOKEN_TTL = process.env.TOKEN_TTL || '30d';
export const COOKIE_NAME = 'mase_token';

export function hashPassword(plain) {
  return bcrypt.hashSync(plain, 10);
}

export function verifyPassword(plain, hash) {
  try {
    return bcrypt.compareSync(plain, hash);
  } catch {
    return false;
  }
}

export function signToken(user) {
  return jwt.sign(
    { id: user.id, username: user.username, role: user.role, name: user.full_name },
    JWT_SECRET,
    { expiresIn: TOKEN_TTL }
  );
}

export function setAuthCookie(res, token) {
  res.cookie(COOKIE_NAME, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production' && process.env.DISABLE_SECURE_COOKIE !== '1',
    maxAge: 1000 * 60 * 60 * 24 * 30,
  });
}

export function clearAuthCookie(res) {
  res.clearCookie(COOKIE_NAME);
}

// Populates req.user from the cookie (if present and valid). Never blocks.
export function attachUser(req, _res, next) {
  const token = req.cookies?.[COOKIE_NAME];
  if (token) {
    try {
      const payload = jwt.verify(token, JWT_SECRET);
      const user = db
        .prepare('SELECT id, username, full_name, role, active FROM users WHERE id = ?')
        .get(payload.id);
      if (user && user.active) req.user = user;
    } catch {
      // invalid / expired token -> treated as anonymous
    }
  }
  next();
}

export function requireAuth(req, res, next) {
  if (!req.user) return res.status(401).json({ error: 'Non autenticato' });
  next();
}

export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: 'Non autenticato' });
    if (!roles.includes(req.user.role)) {
      return res.status(403).json({ error: 'Permesso negato' });
    }
    next();
  };
}
