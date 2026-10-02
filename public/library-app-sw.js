'use strict';
const CACHE = 'cnet-library-app-shell-v1';
const OFFLINE = '/library-app-offline.html';
self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys
        .filter((key) => key.startsWith('cnet-library-app-shell-') && key !== CACHE)
        .map((key) => caches.delete(key)))).then(() => self.clients.claim()));
});
// Live admissions, seats, student details and tests are never placed in offline caches.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || event.request.mode !== 'navigate' || new URL(event.request.url).origin !== self.location.origin) return;
    event.respondWith(fetch(event.request).catch(async () =>
        (await caches.match(OFFLINE, {cacheName: CACHE})) || new Response('Internet connection required.', {status: 503, headers: {'Content-Type': 'text/plain'}})));
});
