import multer from 'multer';
import path from 'node:path';
import fs from 'node:fs';
import crypto from 'node:crypto';
import { DATA_DIR } from '../db.js';

export const UPLOAD_DIR = process.env.UPLOAD_DIR || path.join(DATA_DIR, 'uploads');
if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

const ALLOWED = new Set([
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'image/heic',
  'image/heif',
]);

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, UPLOAD_DIR),
  filename: (_req, file, cb) => {
    const ext = path.extname(file.originalname || '').slice(0, 12) || '.jpg';
    const name = crypto.randomBytes(16).toString('hex') + ext.toLowerCase();
    cb(null, name);
  },
});

export const upload = multer({
  storage,
  limits: { fileSize: 15 * 1024 * 1024, files: 8 }, // 15 MB per file, up to 8 files
  fileFilter: (_req, file, cb) => {
    if (ALLOWED.has(file.mimetype)) return cb(null, true);
    cb(new Error('Formato file non supportato (sono ammesse solo immagini)'));
  },
});
