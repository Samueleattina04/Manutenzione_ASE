# Accesso da fuori azienda + notifiche push (Cloudflare Tunnel)

Guida per rendere **Manutenzione ASE** raggiungibile **anche fuori dalla rete
aziendale** e, di conseguenza, abilitare le **notifiche push sul telefono**
(che richiedono un certificato HTTPS valido pubblico).

Approccio scelto: **Cloudflare Tunnel + Cloudflare Access**.
- Nessuna porta del firewall da aprire (il tunnel è in *uscita* dal server).
- URL pubblico con **certificato HTTPS valido** automatico.
- **Login aziendale** (Access) davanti all'app: solo le persone autorizzate la
  raggiungono, così l'accesso libero degli operatori resta comodo ma protetto.
- Costi: **gratis** fino a 50 utenti; serve solo un **dominio** (gratis se usi
  un sottodominio di uno che avete già, altrimenti ~10 €/anno).

> ⚠️ I passaggi su Cloudflare e sul firewall vanno concordati con l'**IT**:
> cambiano il modo in cui l'azienda è raggiungibile da internet.

---

## 1. Prerequisiti

- Un **dominio** gestibile su Cloudflare (piano **Free**). Esempi:
  - un **sottodominio** di un dominio aziendale già esistente
    (es. `manutenzione.nutkao.it`), oppure
  - un **dominio dedicato** comprato solo per questa app.
- Accesso **amministratore** al server Windows dove gira l'app.

---

## 2. Aggiungere il dominio a Cloudflare

1. Crea un account su <https://dash.cloudflare.com> (gratis).
2. **Add a site** → inserisci il dominio → piano **Free**.
3. Cloudflare ti darà due **nameserver**: l'IT deve impostarli sul dominio
   (o delegare il solo sottodominio). Attendi la conferma "Active".

---

## 3. Installare il tunnel sul server (Zero Trust)

1. Nel dashboard: **Zero Trust** → **Networks** → **Tunnels** → **Create a tunnel**
   → tipo **Cloudflared** → dai un nome (es. `manutenzione-ase`).
2. Scegli **Windows (64-bit)**: Cloudflare mostra un comando `cloudflared ...`
   già pronto con il token del tunnel. **Installalo come servizio** con quel
   comando (da PowerShell come amministratore): così parte da solo all'avvio.
3. Nella scheda **Public Hostname** del tunnel, aggiungi:
   - **Subdomain/Domain:** l'indirizzo pubblico che vuoi (es.
     `manutenzione` + `nutkao.it`).
   - **Service:** `HTTP` → `localhost:80`
     *(usa la porta su cui IIS serve l'app localmente — se è la 87, metti
     `localhost:87`).*
4. Salva. Da questo momento l'app risponde su
   `https://manutenzione.nutkao.it` con certificato valido.

---

## 4. Proteggere l'accesso (Cloudflare Access)

Per far entrare **solo** le persone autorizzate (consigliato, dato che gli
operatori entrano senza password):

1. **Zero Trust** → **Access** → **Applications** → **Add an application** →
   **Self-hosted**.
2. **Application domain:** lo stesso hostname pubblico del tunnel.
3. **Policy:** consenti l'accesso, ad esempio, alle **email aziendali**
   (dominio `@...`) oppure a una lista di indirizzi. Metodo di login: **One-time
   PIN via email** (semplice, nessuna password nuova) o l'identità aziendale.
4. Salva. Ora, prima di vedere l'app, viene chiesto un rapido login aziendale.

---

## 5. Impostazioni dell'app (una volta pubblicata)

Nel file `.env` sul server, quando l'app è raggiungibile dall'indirizzo pubblico:

```
APP_URL=https://manutenzione.nutkao.it
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
```

Poi applica:

```powershell
cd C:\inetpub\wwwroot\Manutenzione_ASE
php artisan config:cache
php artisan route:cache
php artisan view:cache
iisreset
```

> Finché non completi il passaggio, lascia `FORCE_HTTPS`/`SESSION_SECURE_COOKIE`
> **non impostati**: l'accesso interno in http continua a funzionare.
> L'app è già predisposta per riconoscere l'https del tunnel
> (header `X-Forwarded-Proto`).

---

## 6. Notifiche push sul telefono (dopo la pubblicazione)

Le notifiche in tempo reale sul telefono (Web Push / PWA) funzionano **solo con
un certificato HTTPS valido pubblico**: una volta completati i passi qui sopra,
il requisito è soddisfatto.

A quel punto, lato app, si abilita:
- l'installazione come **app** sul telefono ("Aggiungi a schermata Home");
- il consenso alle **notifiche**;
- l'invio automatico di una notifica quando, ad esempio, viene aperta/assegnata
  una richiesta a un manutentore o cambia lo stato di una richiesta per
  l'operatore.

> Nota tecnica: su iPhone le notifiche push web funzionano da iOS 16.4+ e solo
> se l'app è stata **aggiunta alla schermata Home**. Su Android funzionano da
> Chrome senza installazione.
