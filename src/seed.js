import db from './db.js';
import { hashPassword } from './auth.js';

// Default accounts created on first run. Passwords SHOULD be changed after
// the first login (the admin can manage users from the interface).
const DEFAULT_USERS = [
  { username: 'admin', password: 'admin123', full_name: 'Amministratore', role: 'admin' },
  { username: 'operatore', password: 'operatore123', full_name: 'Operatore Demo', role: 'operatore' },
  { username: 'manutentore', password: 'manutentore123', full_name: 'Manutentore Demo', role: 'manutentore' },
];

export function seedUsers() {
  const count = db.prepare('SELECT COUNT(*) AS n FROM users').get().n;
  if (count > 0) return { created: 0 };

  const insert = db.prepare(
    'INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
  );
  const tx = db.transaction((users) => {
    for (const u of users) insert.run(u.username, hashPassword(u.password), u.full_name, u.role);
  });
  tx(DEFAULT_USERS);
  return { created: DEFAULT_USERS.length, users: DEFAULT_USERS };
}

// Allow running `npm run seed` directly.
if (import.meta.url === `file://${process.argv[1]}`) {
  const result = seedUsers();
  if (result.created > 0) {
    console.log(`Creati ${result.created} utenti predefiniti:`);
    for (const u of result.users) {
      console.log(`  - ${u.role.padEnd(12)} username: ${u.username}  password: ${u.password}`);
    }
    console.log('\nIMPORTANTE: cambia le password dopo il primo accesso.');
  } else {
    console.log('Utenti già presenti: nessun utente creato.');
  }
  process.exit(0);
}
