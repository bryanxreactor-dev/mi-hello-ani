// HelloAni Service Worker
const CACHE_NAME = 'helloani-v1';
const STATIC_ASSETS = [
  '/',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/img/favicon.svg',
  '/assets/img/no-poster.svg'
];

// Install - cache static assets
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// Activate - clean old caches
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(names => Promise.all(
      names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n))
    ))
  );
  self.clients.claim();
});

// Fetch - network first, fallback to cache
self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;
  // Skip API and admin requests
  if (e.request.url.includes('/api/') || e.request.url.includes('/admin')) return;

  e.respondWith(
    fetch(e.request)
      .then(response => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(e.request, clone));
        return response;
      })
      .catch(() => caches.match(e.request))
  );
});
