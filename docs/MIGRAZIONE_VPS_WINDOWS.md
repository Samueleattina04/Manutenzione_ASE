# Migrazione su VPS Windows (host.it) — guida completa

Sposta **Manutenzione ASE** dal server interno a un **VPS Windows** pubblico, con
dominio e HTTPS valido. Così l'app è raggiungibile **da fuori azienda** e le
**notifiche push** funzionano. Il VPS è **isolato dalla rete aziendale**: anche
in caso di problemi, non c'è alcun accesso alla LAN Nutkao.

Sostituisci ovunque:
- `DOMINIO` → il tuo dominio (es. `manutenzione-ase.it`)
- `IP-VPS` → l'IP pubblico del VPS (te lo dà host.it)

---

## 0. Prima di iniziare — dal vecchio server (backup dei dati)

Sul server interno attuale, esporta i dati da portare sul VPS:

```powershell
cd C:\inetpub\wwwroot\Manutenzione_ASE
# 1) Database (adatta utente/nome DB al tuo .env):
mysqldump -u manutenzione -p manutenzione_ase > C:\backup\manutenzione_ase.sql
# 2) Foto caricate (cartella allegati):
#    copia l'intera cartella  storage\app\private\uploads
```
Tieni da parte anche il valore di **APP_KEY** dal `.env` attuale (serve per non
invalidare le sessioni; comunque poi rigeneriamo il resto).

---

## 1. Preparare il VPS Windows

Accedi al VPS via **Desktop Remoto** (RDP) con le credenziali di host.it. Poi:

1. **IIS** — Server Manager → *Add Roles and Features* → **Web Server (IIS)**.
   Aggiungi il modulo **CGI** (serve per PHP) e installa **URL Rewrite**
   (dal sito Microsoft/IIS).
2. **PHP 8.2+** — scarica il PHP per Windows (Non-Thread-Safe), scompattalo in
   `C:\php`, e nel `php.ini` **abilita le estensioni**:
   `openssl`, `curl`, `mbstring`, `fileinfo`, `gd`, `pdo_mysql`, `zip`, `xml`,
   `ctype`, `tokenizer`. Configura IIS per usare PHP via **FastCGI** (Handler
   Mappings → aggiungi `*.php` → `C:\php\php-cgi.exe`).
   > `zip` e `gd` servono all'export Excel/icone; `openssl` e `curl` alle
   > notifiche push. Verifica con: `php -m` (devono comparire tutte).
3. **MySQL 8 / MariaDB** — installa e crea database + utente:
   ```sql
   CREATE DATABASE manutenzione_ase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'manutenzione'@'localhost' IDENTIFIED BY 'UNA-PASSWORD-FORTE';
   GRANT ALL PRIVILEGES ON manutenzione_ase.* TO 'manutenzione'@'localhost';
   FLUSH PRIVILEGES;
   ```
4. **Composer** e **Git** per Windows.

---

## 2. Installare l'app sul VPS

```powershell
cd C:\inetpub\wwwroot
git clone https://github.com/Samueleattina04/Manutenzione_ASE.git
cd Manutenzione_ASE
git checkout claude/maintenance-request-app-eueovv
composer install --no-dev --optimize-autoloader
copy .env.example .env
php artisan key:generate
```

Apri il `.env` e imposta (produzione, indirizzo pubblico, notifiche, fuso):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO
APP_TIMEZONE=Europe/Rome

FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=manutenzione_ase
DB_USERNAME=manutenzione
DB_PASSWORD=UNA-PASSWORD-FORTE

# Email (come già configurato): MAIL_MAILER=smtp, ecc.
```

### Importa i dati dal vecchio server
```powershell
# database:
mysql -u manutenzione -p manutenzione_ase < C:\percorso\manutenzione_ase.sql
# foto: copia la cartella uploads dentro
#   C:\inetpub\wwwroot\Manutenzione_ASE\storage\app\private\uploads
```

> Se invece vuoi ripartire pulito (senza dati vecchi): `php artisan migrate --seed`.
> Con i dati importati, esegui solo `php artisan migrate --force` (applica eventuali
> migrazioni nuove).

### Chiavi notifiche push + permessi + cache
```powershell
php artisan push:vapid   # incolla le 3 righe WEBPUSH_ nel .env
icacls "C:\inetpub\wwwroot\Manutenzione_ASE\storage" /grant "IIS_IUSRS:(OI)(CI)M" /T
icacls "C:\inetpub\wwwroot\Manutenzione_ASE\bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)M" /T
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Configura il **sito IIS** puntando la *Physical Path* alla cartella **`public`**
(`...\Manutenzione_ASE\public`) — il `web.config` è già incluso.

---

## 3. Dominio + HTTPS (il pezzo che abilita le notifiche)

1. **DNS:** nel pannello host.it del dominio, crea un **record A**:
   `DOMINIO` → `IP-VPS` (e `www` opzionale). Attendi qualche minuto.
2. **Firewall del VPS:** consenti in ingresso le porte **80** e **443** (HTTP/HTTPS).
   Limita l'**RDP (3389)** ai soli IP aziendali.
   > Qui aprire 80/443 è normale e sicuro: è il firewall del **VPS**, non della
   > rete aziendale (che resta separata).
3. **Certificato HTTPS gratuito** con **win-acme**:
   - scarica `win-acme` sul VPS, eseguilo (`wacs.exe`), scegli il sito IIS e il
     dominio → ottiene un certificato **Let's Encrypt** e lo installa sul binding
     **443**. Crea da solo l'attività di **rinnovo automatico**.
4. Verifica: apri `https://DOMINIO` — deve mostrare il lucchetto valido.

Dopo aver messo `APP_URL`/`FORCE_HTTPS` nel `.env`, rilancia
`php artisan config:cache` e riavvia IIS (`iisreset`).

---

## 4. Cancello d'accesso — codice aziendale (consigliato)

Gli operatori entrano **senza password**: su un sito pubblico conviene un filtro
davanti. L'app ha già integrato un **codice di accesso aziendale**, pensato
apposta per chi non ha email/telefoni aziendali:

- da **Impostazioni → 🔒 Codice di accesso** (come admin), imposti un codice
  condiviso (semplice, da comunicare a voce);
- ogni **dispositivo** lo inserisce **una volta sola** e resta sbloccato (niente
  email, niente PIN); i tablet di reparto li sblocchi tu in fase di installazione,
  così gli operatori non vedono nulla di diverso;
- se il codice viene cambiato, tutti i dispositivi lo reinseriscono (revoca
  immediata). Campo vuoto = filtro disattivato.

> Consiglio: attiva il codice **prima** di comunicare l'indirizzo pubblico.
> (In alternativa, chi preferisce, può ancora usare Cloudflare Access — vedi
> `docs/ACCESSO_ESTERNO.md` — ma per il vostro caso il codice interno è più comodo.)

---

## 5. Notifiche push, riepilogo giornaliero, backup

- **Notifiche push:** già attive con le chiavi VAPID del passo 2. Ogni persona,
  aperta l'app da `https://DOMINIO`, tocca **🔔 Attiva notifiche** (iPhone: prima
  *Aggiungi a schermata Home*).
- **Riepilogo delle 07:00:** crea l'attività pianificata come già fai ora
  (`php artisan richieste:riepilogo`, ogni giorno alle 07:00). Vedi README.
- **Backup automatici (importante, i dati ora sono sul VPS):** attività
  pianificata giornaliera che fa `mysqldump` del database e copia la cartella
  `storage\app\private\uploads`, conservando gli ultimi N giorni. Te la preparo
  io se vuoi.

---

## 6. Go-live

1. Prova `https://DOMINIO` da un telefono **fuori dalla rete aziendale** (rete
   dati) → deve aprirsi e chiedere il login.
2. Attiva le notifiche su un telefono e apri una richiesta di prova → deve
   arrivare la notifica.
3. Comunica a tutti il nuovo indirizzo. Il vecchio sito interno si può spegnere
   (o tenere come riserva qualche giorno).

> Aggiornamenti futuri dell'app: sul VPS `git pull` + `migrate --force` +
> `config:cache`/`route:cache`/`view:cache` + `iisreset`.
