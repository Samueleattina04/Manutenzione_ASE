# 🔧 Manutenzione ASE

Applicativo web (**PHP / Laravel / MySQL**) per la **gestione delle richieste di
manutenzione**. Sostituisce il vecchio modulo Google + foglio di risposte,
aggiungendo tutto ciò che prima mancava: presa in carico, cambio di stato,
storico degli interventi con data e ora, descrizione di quello che il
manutentore ha fatto e **foto** sia del problema che della soluzione.

Due profili con viste diverse:

- **Operatore** — apre le richieste (stesso modulo di prima) e segue l'avanzamento.
- **Manutentore** — riceve subito le richieste, le prende in carico, aggiorna lo
  stato e registra l'intervento.
- **Amministratore** — come il manutentore, più la gestione degli utenti.

---

## Cosa risolve (rispetto al modulo Google)

| Problema del vecchio modulo | Soluzione in Manutenzione ASE |
|---|---|
| Non si registrava nulla dell'intervento del manutentore | Ogni intervento è salvato con autore, data/ora e descrizione |
| Non si sapeva quando la richiesta veniva risolta | Vengono registrati apertura, presa in carico e risoluzione, con il tempo impiegato |
| Nessuna presa in carico | Il manutentore prende in carico la richiesta con un click |
| Stato non visibile all'operatore | L'operatore vede lo stato aggiornato quasi in tempo reale |
| L'operatore non poteva allegare foto del problema | Foto del problema caricabili dal telefono al momento della richiesta |
| Il manutentore non poteva allegare foto della soluzione | Foto della soluzione allegabili ad ogni intervento |

### Stati di una richiesta

`Aperta` → `Presa in carico` → `In corso` → `Risolta parzialmente` →
`Risolta completamente` → `Chiusa`

---

## Requisiti

- **PHP 8.2+** con le estensioni: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`,
  `openssl`, `tokenizer`, `xml`, `ctype`, `curl`
- **Composer 2**
- **MySQL 8** (o MariaDB 10.4+)

Oppure semplicemente **Docker** (vedi sotto).

---

## Installazione (manuale)

```bash
# 1) Dipendenze PHP
composer install

# 2) Configurazione
cp .env.example .env
php artisan key:generate
# apri .env e imposta i dati del database (DB_DATABASE, DB_USERNAME, DB_PASSWORD)

# 3) Crea il database MySQL (una volta)
#    CREATE DATABASE manutenzione_ase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#    e un utente con permessi su quel database.

# 4) Tabelle + utenti iniziali
php artisan migrate --seed

# 5) Avvio (sviluppo/rete interna)
php artisan serve --host=0.0.0.0 --port=8000
```

Apri il browser su **http://SERVER:8000**

### Accesso

Aprendo il sito si sceglie il profilo:

- **Operatore** → entra **subito, senza username e password** (compila poi il
  proprio nome nel modulo della richiesta).
- **Manutentore / Amministratore** → accesso con **username e password**.

Utenti creati al primo avvio:

| Ruolo | Username | Password |
|---|---|---|
| Amministratore | `admin` | `admin123` |
| Manutentore | `manutentore` | `manutentore123` |
| Operatore (accesso libero) | `operatore` | *(non serve: entra dal pulsante “Operatore”)* |

> ⚠️ **Cambia subito le password** dopo il primo accesso (menu in alto a destra →
> *Cambia password*) e crea gli utenti reali dalla sezione **Utenti** (come admin).

---

## Avvio con Docker

Il modo più rapido: avvia sia l'applicativo che MySQL con un comando.

```bash
docker compose up -d --build
```

- Applicativo su **http://localhost:8000**
- Migrazioni e utenti iniziali vengono creati automaticamente.
- Le foto e il database restano nei volumi Docker (`uploads`, `dbdata`).

> Per la produzione conviene generare una chiave stabile e inserirla in
> `docker-compose.yml` (`APP_KEY`):
> ```bash
> php artisan key:generate --show
> ```

---

## Come si usa

### Operatore
1. Entra dalla pagina iniziale con il pulsante **Operatore** (senza password) e apre **Nuova** richiesta.
2. Compila il modulo (impianto, macchinario, reparto, descrizione, priorità,
   note, operatore) e può **allegare foto del problema**.
3. Dalla lista **Richieste** segue lo stato **delle proprie** richieste.

> **Privacy tra operatori:** ogni operatore vede solo le richieste aperte **dal
> proprio dispositivo** (telefono/PC), non quelle degli altri operatori. Il
> collegamento è un cookie permanente del dispositivo (durata ~2 anni) che
> **resta anche dopo il logout**: uscendo e rientrando come operatore si
> rivedono le proprie richieste. Vale sullo stesso dispositivo/browser (se si
> cambia dispositivo o si cancellano i dati del browser, le richieste
> precedenti non sono più collegate). Manutentori e amministratori vedono
> invece **tutte** le richieste.

### Manutentore
1. Vede tutte le richieste ordinate per priorità (rosso/giallo/verde) e data.
2. Apre una richiesta e clicca **Prendi in carico**.
3. Aggiorna lo stato, scrive la **descrizione dell'intervento** e allega le
   **foto della soluzione**.

### Amministratore
- Tutto quello che fa il manutentore, più la sezione **Utenti** per creare,
  modificare, disattivare gli account e reimpostare le password.

---

## Aggiornamenti automatici

L'elenco delle richieste e il dettaglio si aggiornano da soli ogni pochi secondi
(polling), così il manutentore vede comparire subito le nuove richieste e
l'operatore vede il cambio di stato senza ricaricare la pagina.

---

## Deploy in produzione (nota)

Per un uso intensivo si consiglia di servire l'app con **Nginx + PHP-FPM** e di
puntare il web server sulla cartella `public/`, invece di `php artisan serve`.
Ricordarsi di eseguire una volta:

```bash
php artisan config:cache
php artisan route:cache
```

## Backup

- **Database**: backup della base dati MySQL `manutenzione_ase`
  (`mysqldump manutenzione_ase > backup.sql`).
- **Foto**: cartella `storage/app/private/uploads` (o il volume Docker `uploads`).

---

## Struttura del progetto (Laravel)

```
app/
  Http/Controllers/   Login, Richieste, Utenti, Allegati, Profilo
  Http/Middleware/    EnsureRole (controllo dei ruoli)
  Models/             User, MaintenanceRequest, RequestUpdate, Attachment
config/manutenzione.php   Impianti, reparti, priorità, stati
database/migrations/  Struttura delle tabelle
database/seeders/     Utenti iniziali
resources/views/      Interfaccia (Blade)
public/css, public/js Stile e piccole interazioni (nessun build necessario)
routes/web.php        Rotte
```
