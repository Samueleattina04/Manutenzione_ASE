# 🔧 Manutenzione ASE

Applicativo web (**PHP / Laravel / MySQL**) per la **gestione delle richieste di
manutenzione**. Sostituisce il vecchio modulo Google + foglio di risposte,
aggiungendo tutto ciò che prima mancava: presa in carico, cambio di stato,
storico degli interventi con data e ora, descrizione di quello che il
manutentore ha fatto e **foto** sia del problema che della soluzione.

Profili con viste diverse:

- **Operatore** — apre le richieste (stesso modulo di prima) e segue l'avanzamento.
  Accede **senza credenziali** scegliendo il reparto.
- **Manutentore interno** — vede **tutte** le richieste di *manutenzione interna*,
  le prende in carico e registra l'intervento.
- **Manutentore esterno** — vede **solo** le richieste di *manutenzione esterna*
  assegnate a lui.
- **Manutentore straordinario** — l'unico che gestisce la *manutenzione
  straordinaria*: le richieste gli vengono assegnate **in automatico**.
- **Amministratore** — vede tutto, gestisce gli utenti e assegna/riassegna le
  richieste (può anche cambiarne il tipo di manutenzione).

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

- **Operatore** → sceglie il **reparto** ed entra **senza username e password**
  (compila poi il proprio nome nel modulo della richiesta).
- **Manutentore / Amministratore** → accesso con **username e password**.

Utenti creati al primo avvio:

| Ruolo | Username | Password |
|---|---|---|
| Amministratore | `admin` | `admin123` |
| Manutentore interno | `manutentore` | `manutentore123` |
| Manutentore esterno (demo) | `esterno` | `esterno123` |
| Manutentore straordinario (demo) | `straordinario` | `straordinario123` |
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

> **Visibilità per reparto:** l'operatore all'accesso sceglie un **reparto** e
> vede tutte le richieste aperte dagli operatori entrati con **quello stesso
> reparto** (da qualunque dispositivo, anche dopo il logout: basta rientrare
> scegliendo lo stesso reparto). Questo "reparto d'accesso" serve solo a
> raggruppare la visibilità e **non è mostrato ai manutentori**: a loro appare
> soltanto il reparto scelto nel modulo della richiesta. Manutentori e
> amministratori vedono **tutte** le richieste.

### Manutentore
1. Vede le richieste di sua competenza, ordinate per priorità (rosso/giallo/verde) e data.
2. Apre una richiesta e clicca **Prendi in carico**.
3. Aggiorna lo stato, scrive la **descrizione dell'intervento** e allega le
   **foto della soluzione**.

### Amministratore
- Tutto quello che fa il manutentore, più la sezione **Utenti** per creare,
  modificare, disattivare o **eliminare** gli account e reimpostare le password.
- Dal dettaglio di una richiesta può **cambiarne il destinatario** (interna /
  straordinaria / esterna) e, per le esterne, **scegliere il manutentore**.

### Destinatario e assegnazione del manutentore
Ogni richiesta ha un **destinatario** che ne determina chi la gestisce:

- **Manutenzione interna** → **nessuna assegnazione**: la vedono **tutti i
  manutentori interni**.
- **Manutenzione straordinaria** → assegnata **in automatico** all'unico
  **manutentore straordinario** già all'apertura della richiesta, con
  **email immediata**.
- **Manutenzione esterna** → l'**amministratore** sceglie il **manutentore
  esterno** dal dettaglio; alla scelta parte l'**email** di assegnazione. Ogni
  esterno vede **solo** le proprie richieste (un esterno che ripara i muletti
  non vede quelle destinate a un altro).

L'amministratore può in ogni momento **cambiare il tipo di manutenzione** di una
richiesta (es. da straordinaria a esterna, o a interna): il sistema riassegna il
manutentore giusto (o azzera l'assegnazione per le interne) e invia l'email di
notifica se cambia il manutentore.

- **Notifica email:** se al manutentore è associata un'email (in *Utenti*),
  all'assegnazione parte in automatico un'**email di riepilogo** della
  richiesta. Utile perché gli esterni/straordinari non accedono da remoto:
  ricevono l'avviso e, quando sono in azienda, entrano nell'applicativo per
  registrare l'intervento. Per l'invio reale configura l'SMTP aziendale nel
  `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, ecc. — vedi `.env.example`). Con
  `MAIL_MAILER=log` le email vengono solo scritte nei log, non inviate.

### Tempo di intervento previsto
Quando una richiesta arriva, il **manutentore** può indicare **entro quanto
tempo sarà in reparto** per la sistemazione (dal dettaglio → *Tempo di
intervento*). L'orario previsto viene mostrato in evidenza nel dettaglio, così
anche l'**operatore** sa quando aspettarsi l'intervento.

### Livelli di priorità
- 🟢 **Verde – Bassa:** intervento entro **8 ore**.
- 🟡 **Giallo – Media:** intervento entro **4 ore**.
- 🔴 **Rosso – Urgente:** intervento entro **30 minuti**.

---

## Riepilogo giornaliero via email (Excel)

Ogni mattina alle **07:00** (ora italiana) parte in automatico un'email di
riepilogo delle **richieste ancora aperte**, con in **allegato un file Excel**
(`.xlsx`) contenente l'elenco completo e tutti i dettagli:

- agli **amministratori** arrivano **tutte** le richieste aperte;
- ai **manutentori interni** arrivano tutte le richieste di *manutenzione
  interna* aperte;
- ai **manutentori esterni** arrivano solo le *esterne* aperte a loro assegnate;
- al **manutentore straordinario** arrivano le richieste di *manutenzione
  straordinaria* aperte.

Chi non ha alcuna richiesta aperta in quel momento **non riceve** l'email
(niente messaggi a vuoto). L'email viene inviata solo agli utenti che hanno un
**indirizzo email** configurato (sezione *Utenti*), usando lo stesso SMTP delle
notifiche (`.env`).

Comando eseguito dallo scheduler:

```bash
php artisan richieste:riepilogo          # invia il riepilogo
php artisan richieste:riepilogo --dry    # prova senza inviare (mostra i destinatari)
```

**Attivazione dello scheduler** (necessaria una volta sola sul server):

- **Windows / IIS (Utilità di pianificazione) — metodo semplice consigliato:**
  crea un'attività di base che parte **ogni giorno alle 07:00** ed esegue
  **direttamente** il comando del riepilogo.
  - *Attivazione:* Ogni giorno, ora **07:00**.
  - *Azione:* Avvia programma
    - **Programma o script:** percorso di `php.exe` (trovalo con `where php`,
      es. `C:\php\php.exe`)
    - **Argomenti:** `artisan richieste:riepilogo`
    - **Inizia in:** `C:\inetpub\wwwroot\Manutenzione_ASE`
  - Nelle proprietà dell'attività, spunta *Esegui indipendentemente dalla
    connessione dell'utente* e *Esegui con i privilegi più elevati*.

  Windows usa l'ora locale del server (italiana) e gestisce da solo il
  passaggio ora legale/solare, quindi le 07:00 restano corrette tutto l'anno.
  Con questo metodo il comando viene lanciato direttamente e non serve lo
  scheduler interno di Laravel.

- **Linux (cron), oppure Windows con scheduler Laravel:** in alternativa si può
  usare lo scheduler interno (definito in `routes/console.php`, fissato su
  **Europe/Rome**) facendo girare `schedule:run` **ogni minuto**:
  ```
  * * * * * cd /percorso/app && php artisan schedule:run >> /dev/null 2>&1
  ```
  In questo caso è lo scheduler a lanciare il riepilogo solo alle 07:00.

---

## Aggiornamenti automatici

L'elenco delle richieste e il dettaglio si aggiornano da soli ogni pochi secondi
(polling), così il manutentore vede comparire subito le nuove richieste e
l'operatore vede il cambio di stato senza ricaricare la pagina.

## Codice di accesso aziendale (per l'uso pubblico)

Quando l'app è raggiungibile da fuori azienda, si può attivare un **codice di
accesso** condiviso (da *Impostazioni → 🔒 Codice di accesso*, come admin): ogni
dispositivo lo inserisce **una volta sola** e resta sbloccato, senza email né
account. Utile perché gli operatori entrano senza password: il codice fa da
"porta d'ingresso". Campo vuoto = disattivato (app aperta, adatto alla sola rete
interna).

## Notifiche push sul telefono

L'app può inviare **notifiche push in tempo reale** sul telefono (Web Push, senza
dipendenze esterne): nuova richiesta interna → ai manutentori interni; richiesta
esterna/straordinaria assegnata → al manutentore relativo; cambio stato/intervento
→ agli operatori del reparto. Richiede un **HTTPS pubblico valido** (vedi
`docs/ACCESSO_ESTERNO.md`) e le chiavi VAPID generate con `php artisan push:vapid`.
Ogni utente attiva le notifiche dal menu (**🔔 Attiva notifiche**).

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
