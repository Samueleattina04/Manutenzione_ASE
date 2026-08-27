import express from 'express';
import cookieParser from 'cookie-parser';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { attachUser, requireAuth } from './auth.js';
import { seedUsers } from './seed.js';
import { addClient } from './lib/events.js';
import { IMPIANTI, REPARTI, PRIORITA, STATI, STATI_MANUTENTORE } from './lib/constants.js';

import authRoutes from './routes/auth.routes.js';
import usersRoutes from './routes/users.routes.js';
import requestsRoutes from './routes/requests.routes.js';
import attachmentsRoutes from './routes/attachments.routes.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PUBLIC_DIR = path.join(__dirname, '..', 'public');
const PORT = process.env.PORT || 3000;

// Create default accounts on first run.
const seedResult = seedUsers();
if (seedResult.created > 0) {
  console.log(`[seed] Creati ${seedResult.created} utenti predefiniti (admin / operatore / manutentore).`);
}

const app = express();
app.disable('x-powered-by');
app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(attachUser);

// Static configuration for the form (options come from the server).
app.get('/api/meta', (_req, res) => {
  res.json({
    impianti: IMPIANTI,
    reparti: REPARTI,
    priorita: PRIORITA,
    stati: STATI,
    stati_manutentore: STATI_MANUTENTORE,
  });
});

// Server-Sent Events: browsers get pushed a tiny signal on every change.
app.get('/api/events', requireAuth, (req, res) => {
  res.set({
    'Content-Type': 'text/event-stream',
    'Cache-Control': 'no-cache',
    Connection: 'keep-alive',
    'X-Accel-Buffering': 'no',
  });
  res.flushHeaders?.();
  res.write('retry: 5000\n\n');
  addClient(res);
  const ping = setInterval(() => {
    try {
      res.write(': ping\n\n');
    } catch {
      clearInterval(ping);
    }
  }, 25000);
  req.on('close', () => clearInterval(ping));
});

app.use('/api/auth', authRoutes);
app.use('/api/users', usersRoutes);
app.use('/api/requests', requestsRoutes);
app.use('/api/attachments', attachmentsRoutes);

app.get('/api/health', (_req, res) => res.json({ ok: true }));

// Static frontend.
app.use(express.static(PUBLIC_DIR, { index: false, extensions: ['html'] }));

// Everything else -> the single-page app (client-side routing).
app.get(/^(?!\/api\/).*/, (_req, res) => {
  res.sendFile(path.join(PUBLIC_DIR, 'index.html'));
});

// Central error handler (multer errors, etc).
app.use((err, _req, res, _next) => {
  if (err && err.message) {
    const status = err.status || (err.code === 'LIMIT_FILE_SIZE' ? 413 : 400);
    return res.status(status).json({ error: err.message });
  }
  res.status(500).json({ error: 'Errore interno del server' });
});

app.listen(PORT, () => {
  console.log(`Manutenzione ASE in ascolto su http://localhost:${PORT}`);
});
