const CACHE_NAME = 'sodium-static-v61';
const STATIC_ASSETS = [
    '/css/app.css?v=20261112-01',
    '/css/themes.css?v=20260820-02',
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
    event.waitUntil((async()=>{
        let notification={title:'Sodium — Nouveau courrier',body:'Nouveau courrier reçu',account_line:'',url:'/index.php',id:'fallback'};
        try{const response=await fetch('/api/push-notification.php',{credentials:'include',cache:'no-store',headers:{Accept:'application/json'}});if(response.ok)notification={...notification,...await response.json()};}catch(error){}
        const body=[notification.body,notification.account_line].filter(Boolean).join('\n');
        await self.registration.showNotification(notification.title,{body,icon:'/assets/icons/pwa-192.png',badge:'/assets/icons/favicon-64.png',tag:`sodium-new-mail-${notification.id}`,renotify:true,data:{url:notification.url||'/index.php'}});
    })());
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const targetUrl=event.notification.data?.url||'/index.php';
    event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(windows => {
        for (const client of windows) {
            if ('focus' in client) { client.navigate(targetUrl); return client.focus(); }
        }
        return clients.openWindow(targetUrl);
    }));
});
