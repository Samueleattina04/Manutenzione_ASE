# 🔧 Manutenzione ASE

Applicativo web per la **gestione delle richieste di manutenzione**. Sostituisce il
vecchio modulo Google + foglio di risposte, aggiungendo tutto ciò che prima mancava:
presa in carico, cambio di stato in tempo reale, storico degli interventi con data e
ora, descrizione di quello che il manutentore ha fatto e **foto** sia del problema che
della soluzione.

Due profili con viste diverse:

- **Operatore** — apre le richieste (stesso modulo di prima) e segue l'avanzamento.
- **Manutentore** — riceve subito le richieste, le prende in carico, aggiorna lo stato
  e registra l'intervento.
- **Amministratore** — come il manutentore, più la gestione degli utenti.

---

## Cosa risolve (rispetto al modulo Google)

| Problema del vecchio modulo | Soluzione in Manutenzione ASE |
|---|---|
| Non si registrava nulla dell'intervento del manutentore | Ogni intervento è salvato con autore, data/ora e descrizione |
| Non si sapeva quando la richiesta veniva risolta | Vengono registrati apertura, presa in carico e risoluzione, con il tempo impiegato |
| Nessuna presa in carico | Il manutentore prende in carico la richiesta con un click |
| Stato non visibile all'operatore | L'operatore vede lo stato aggiornato in tempo reale |
| L'operatore non poteva allegare foto del problema | Foto del problema caricabili dal telefono al momento della richiesta |
| Il manutentore non poteva allegare foto della soluzione | Foto della soluzione allegabili ad ogni intervento |

### Stati di una richiesta

`Aperta` → `Presa in carico` → `In corso` → `Risolta parzialmente` → `Risolta
completamente` → `Chiusa`

Ogni cambio di stato è visibile immediatamente a operatori e manutentori.

---

## Requisiti

- **Node.js 18 o superiore** (consigliato 20/22), oppure **Docker**.

Nessun database esterno da installare: i dati sono salvati in un file SQLite dentro la
cartella `data/` (comprese le foto in `data/uploads/`).

---

## Avvio rapido (Node.js)

```bash
npm install
npm start
```

Poi apri il browser su **http://localhost:3000**

Al primo avvio vengono creati automaticamente tre utenti di prova:

| Ruolo | Username | Password |
|---|---|---|
| Amministratore | `admin` | `admin123` |
| Operatore | `operatore` | `operatore123` |
| Manutentore | `manutentore` | `manutentore123` |

> ⚠️ **Cambia subito le password** dopo il primo accesso (menu in alto a destra →
> *Cambia password*) e crea gli utenti reali dalla sezione **Utenti** (come admin).

---

## Avvio con Docker

Con Docker Compose (consigliato):

```bash
docker compose up -d --build
```

Oppure con Docker semplice:

```bash
docker build -t manutenzione-ase .
docker run -d -p 3000:3000 -v manutenzione-data:/data \
  -e JWT_SECRET="una-stringa-lunga-e-casuale" \
  --name manutenzione-ase manutenzione-ase
```

I dati (database + foto) restano nel volume `manutenzione-data` (montato su `/data`) e
sopravvivono ai riavvii e agli aggiornamenti del container.

---

## Configurazione

Le impostazioni si passano tramite variabili d'ambiente (vedi `.env.example`):

| Variabile | Default | Descrizione |
|---|---|---|
| `PORT` | `3000` | Porta del server web |
| `JWT_SECRET` | *(valore di sviluppo)* | **Da cambiare in produzione**: firma i token di sessione |
| `DATA_DIR` | `./data` | Cartella di database e foto |
| `NODE_ENV` | — | Impostare a `production` in produzione |
| `DISABLE_SECURE_COOKIE` | — | Impostare a `1` se si usa `production` **senza HTTPS** (es. rete interna) |

> In `production` i cookie di login vengono inviati solo su HTTPS. Se il server è
> raggiungibile solo via `http://` (tipico su rete aziendale interna), imposta
> `DISABLE_SECURE_COOKIE=1`, altrimenti il login non funziona.

---

## Come si usa

### Operatore
1. Accede e apre **Nuova** richiesta.
2. Compila il modulo (impianto, macchinario, reparto, descrizione, priorità, note,
   operatore) e può **allegare foto del problema**.
3. Dalla lista **Richieste** segue lo stato di ogni segnalazione in tempo reale.

### Manutentore
1. Vede tutte le richieste ordinate per priorità (rosso/giallo/verde) e data.
2. Apre una richiesta e clicca **Prendi in carico**.
3. Aggiorna lo stato (in corso, risolta parzialmente/completamente, chiusa), scrive la
   **descrizione dell'intervento** e allega le **foto della soluzione**.

### Amministratore
- Tutto quello che fa il manutentore, più la sezione **Utenti** per creare, modificare,
  disattivare gli account e reimpostare le password.

---

## Aggiornamenti in tempo reale

L'interfaccia si aggiorna da sola (tecnologia Server-Sent Events, con aggiornamento
periodico di riserva): quando un operatore apre una richiesta, i manutentori la vedono
comparire subito, e quando un manutentore cambia stato l'operatore lo vede in tempo
reale.

---

## Backup

Per salvare tutti i dati è sufficiente copiare la cartella `data/` (o il volume Docker
`manutenzione-data`). Contiene il database `manutenzione.db` e tutte le foto.

---

## Struttura del progetto

```
src/
  server.js              Server Express e rotte principali
  db.js                  Database SQLite e schema
  auth.js                Autenticazione (login con cookie sicuro)
  seed.js                Creazione utenti iniziali
  lib/constants.js       Impianti, reparti, priorità, stati
  lib/uploads.js         Gestione upload foto
  lib/events.js          Aggiornamenti in tempo reale (SSE)
  routes/                Rotte API (auth, richieste, utenti, allegati)
public/
  index.html             Interfaccia web (single-page)
  css/styles.css         Stile
  js/                    Logica dell'interfaccia
```

## Tecnologie

Node.js · Express · SQLite (better-sqlite3) · autenticazione JWT su cookie ·
frontend in JavaScript senza framework · nessun servizio esterno richiesto.
