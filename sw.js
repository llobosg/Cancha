// sw.js
const CACHE_NAME = 'cancha-v1';
const urlsToCache = [
  '/',
  '/pages/index.php',
  '/styles.css',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png'
];

// Instalación
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
  );
});

// Fetch con caché
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Si está en caché, devolverlo
        if (response) {
          return response;
        }
        // Si no, ir a red
        return fetch(event.request);
      })
  );
});