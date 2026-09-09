/* Service worker per le notifiche push di Manutenzione ASE */

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = {}; }

    var title = data.title || 'Manutenzione ASE';
    var options = {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url || '/' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (var i = 0; i < list.length; i++) {
                var c = list[i];
                if ('focus' in c) {
                    if ('navigate' in c) { try { c.navigate(url); } catch (e) {} }
                    return c.focus();
                }
            }
            if (self.clients.openWindow) { return self.clients.openWindow(url); }
        })
    );
});
