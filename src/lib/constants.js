// Domain constants shared across the application.
// These mirror the fields of the original Google Form ("Richiesta Manutenzione").

export const IMPIANTI = ['Pisti', 'Vincente', 'Madero quality', 'Madero Pastry'];

export const REPARTI = [
  'Madero Produzione',
  'Madero Sgusciatura',
  'Magazzino',
  'Celle',
  'Pisti Crema',
  'Pisti cioccolateria',
  'Pisti Croccanti e biscotti',
  'Pisti confezionamento e cella SL',
  'Mag Imballi',
  'Vincente',
  'Uffici',
  'Esterno',
];

export const PRIORITA = [
  { value: 'verde', label: 'Verde – In coda', short: 'Verde', color: '#2e7d32', rank: 1 },
  { value: 'giallo', label: 'Giallo – Entro la giornata', short: 'Giallo', color: '#f9a825', rank: 2 },
  { value: 'rosso', label: 'Rosso – Blocco produzione. Urgente!!!', short: 'Rosso', color: '#c62828', rank: 3 },
];

// Lifecycle of a maintenance request.
export const STATI = [
  { value: 'aperta', label: 'Aperta', color: '#1565c0', done: false },
  { value: 'presa_in_carico', label: 'Presa in carico', color: '#6a1b9a', done: false },
  { value: 'in_corso', label: 'In corso', color: '#ef6c00', done: false },
  { value: 'risolta_parzialmente', label: 'Risolta parzialmente', color: '#9e9d24', done: false },
  { value: 'risolta', label: 'Risolta completamente', color: '#2e7d32', done: true },
  { value: 'chiusa', label: 'Chiusa', color: '#546e7a', done: true },
];

export const RUOLI = ['operatore', 'manutentore', 'admin'];

// Which statuses a maintenance technician may move a request to.
export const STATI_MANUTENTORE = [
  'presa_in_carico',
  'in_corso',
  'risolta_parzialmente',
  'risolta',
  'chiusa',
];

export const PRIORITA_VALUES = PRIORITA.map((p) => p.value);
export const STATI_VALUES = STATI.map((s) => s.value);

export function isValidPriorita(v) {
  return PRIORITA_VALUES.includes(v);
}

export function isValidStato(v) {
  return STATI_VALUES.includes(v);
}

export function statoIsDone(v) {
  const s = STATI.find((x) => x.value === v);
  return s ? s.done : false;
}
