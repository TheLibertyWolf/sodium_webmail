const CACHE_NAME = 'sodium-static-v51';
const STATIC_ASSETS = [
    '/css/app.css?v=20260820-02',
    '/assets/vendor/bootstrap/bootstrap.min.css',
    '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
    '/assets/vendor/bootstrap-icons/bootstrap-icons.css',
    '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
    '/js/i18n.js?v=20260819-01',
    '/assets/icons/favicon-64.png',
    '/assets/icons/apple-touch-180.png',
    '/assets/icons/pwa-192.png',
    '/assets/icons/pwa-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin || request.mode === 'navigate' || url.pathname.endsWith('.php')) return;
    event.respondWith(
        caches.match(request).then(cached => {
            const fresh = fetch(request).then(response => {
                if (response.ok) caches.open(CACHE_NAME).then(cache => cache.put(request, response.clone()));
                return response;
            });
            return cached || fresh;
        })
    );
});

self.addEventListener('push', event => {
    event.waitUntil(self.registration.showNotification('Sodium — Nouveau message', {
        body: 'Du nouveau courrier est disponible dans votre boîte unifiée.',
        icon: '/assets/icons/pwa-192.png',
        badge: '/assets/icons/favicon-64.png',
        tag: 'sodium-new-mail',
        renotify: true,
        data: {url: '/index.php'}
    }));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(windows => {
        for (const client of windows) {
            if ('focus' in client) { client.navigate('/index.php'); return client.focus(); }
        }
        return clients.openWindow('/index.php');
    }));
});
