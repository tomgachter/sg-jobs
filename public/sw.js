const VERSION = 'sgjobs-sw-v1';
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URLS = [
  '/wp-content/plugins/sg-jobs/dist/jobsheet.js',
  '/wp-content/plugins/sg-jobs/dist/jobsheet.css'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(OFFLINE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }

  if (request.url.includes('/wp-json/sgjobs/v1/jobs/') && request.method === 'POST') {
    event.respondWith(fetch(request));
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => cached || fetch(request).then((response) => {
      const copy = response.clone();
      caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
      return response;
    }))
  );
});
