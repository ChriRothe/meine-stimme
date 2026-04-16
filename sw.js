// ============================================================
//  MEINE STIMME – Service Worker
//  Datei: sw.js (im Wurzelverzeichnis)
//
//  Cacht die App-Shell für Offline-Betrieb.
//  API-Requests werden immer live abgerufen (kein Cache).
// ============================================================

const CACHE_NAME = 'meine-stimme-v1';

// Diese Dateien werden beim ersten Aufruf gecacht
const SHELL_FILES = [
  '/',
  '/index.html',
  '/assets/logo.svg',
  '/assets/icon-192.svg',
  '/assets/icon-512.svg',
  '/assets/fonts/nunito-v26-latin-regular.woff2',
  '/assets/fonts/nunito-v26-latin-700.woff2',
  '/assets/fonts/nunito-v26-latin-900.woff2',
];

// Installation: App-Shell cachen
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      // Einzeln cachen – schlägt ein File fehl, bricht nicht alles ab
      return Promise.allSettled(
        SHELL_FILES.map(url => cache.add(url).catch(() => {}))
      );
    })
  );
  self.skipWaiting();
});

// Aktivierung: alte Caches löschen
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// Fetch: API immer live, Rest aus Cache mit Network-Fallback
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // API-Requests immer live (kein Cache)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // App-Shell: Cache first, dann Network
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        // Erfolgreiche Responses nachcachen
        if (response && response.status === 200 && response.type === 'basic') {
          const toCache = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, toCache));
        }
        return response;
      }).catch(() => {
        // Offline-Fallback: Hauptseite ausliefern
        if (event.request.destination === 'document') {
          return caches.match('/index.html');
        }
      });
    })
  );
});
