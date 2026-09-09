/* Iscrizione alle notifiche push (Web Push) per Manutenzione ASE */
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = meta ? meta.getAttribute('content') : '';

    function supported() {
        return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
    }

    function urlB64ToUint8(base64) {
        var pad = '='.repeat((4 - (base64.length % 4)) % 4);
        var b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(b64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) { arr[i] = raw.charCodeAt(i); }
        return arr;
    }

    function registerSW() {
        return navigator.serviceWorker.register('/sw.js');
    }

    async function enable(btn) {
        if (!supported()) {
            alert('Questo dispositivo/browser non supporta le notifiche push. Su iPhone: aggiungi prima l’app alla schermata Home.');
            return;
        }
        try {
            var cfg = await (await fetch('/push/chiave', { headers: { 'Accept': 'application/json' } })).json();
            if (!cfg.enabled || !cfg.key) {
                alert('Le notifiche non sono ancora state attivate sul server. Riprova più tardi.');
                return;
            }
            var perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                alert('Permesso notifiche non concesso. Puoi attivarlo dalle impostazioni del browser.');
                return;
            }
            var reg = await registerSW();
            await navigator.serviceWorker.ready;
            var sub = await reg.pushManager.getSubscription();
            if (!sub) {
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlB64ToUint8(cfg.key)
                });
            }
            var res = await fetch('/push/iscrivi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(sub)
            });
            if (res.ok) {
                alert('Notifiche attivate su questo dispositivo. ✅');
                if (btn) { btn.textContent = '🔔 Notifiche attive'; }
            } else {
                alert('Non è stato possibile registrare le notifiche. Riprova.');
            }
        } catch (e) {
            alert('Errore durante l’attivazione delle notifiche: ' + (e && e.message ? e.message : e));
        }
    }

    // Registra il service worker in background (per ricevere i push se già iscritti).
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { registerSW().catch(function () {}); });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-push-enable]');
        if (btn) { e.preventDefault(); enable(btn); }
    });
})();
