import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = process.env.DATA_DIR || path.join(__dirname, '..', 'data');

if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

const DB_PATH = process.env.DB_PATH || path.join(DATA_DIR, 'manutenzione.db');

const db = new Database(DB_PATH);
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name     TEXT NOT NULL,
    role          TEXT NOT NULL CHECK (role IN ('operatore','manutentore','admin')),
    active        INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS requests (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    impianto       TEXT NOT NULL,
    impianto_altro TEXT,
    macchinario    TEXT NOT NULL,
    reparto        TEXT,
    descrizione    TEXT,
    priorita       TEXT NOT NULL DEFAULT 'verde',
    note           TEXT,
    operatore      TEXT NOT NULL,
    status         TEXT NOT NULL DEFAULT 'aperta',
    created_by     INTEGER REFERENCES users(id),
    assigned_to    INTEGER REFERENCES users(id),
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now')),
    taken_at       TEXT,
    resolved_at    TEXT
  );

  CREATE TABLE IF NOT EXISTS updates (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id  INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users(id),
    status      TEXT,
    note        TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS attachments (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id    INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    update_id     INTEGER REFERENCES updates(id) ON DELETE SET NULL,
    kind          TEXT NOT NULL DEFAULT 'problema' CHECK (kind IN ('problema','soluzione')),
    filename      TEXT NOT NULL,
    original_name TEXT,
    mime_type     TEXT,
    size          INTEGER,
    uploaded_by   INTEGER REFERENCES users(id),
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE INDEX IF NOT EXISTS idx_requests_status   ON requests(status);
  CREATE INDEX IF NOT EXISTS idx_requests_created  ON requests(created_at);
  CREATE INDEX IF NOT EXISTS idx_updates_request   ON updates(request_id);
  CREATE INDEX IF NOT EXISTS idx_attach_request    ON attachments(request_id);
`);

export { db, DATA_DIR, DB_PATH };
export default db;
