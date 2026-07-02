/**
 * ColdChain EMS service worker.
 *
 * Deliberately minimal: network-first for everything, with the app shell
 * cached as an offline fallback for navigations. Its main job is satisfying
 * PWA installability (Add to Home Screen / TWA); API data is never cached —
 * operators must not act on stale readings.
 */
const SHELL_CACHE = 'coldchain-shell-v1'
const SHELL_URLS = ['/', '/index.html', '/manifest.webmanifest', '/icons/icon-192.png']

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_URLS)).then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== SHELL_CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  const { request } = event

  // Never intercept API traffic.
  if (request.url.includes('/api/')) return

  // Navigations: network first, cached shell when offline.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/index.html')),
    )
    return
  }

  // Static assets: cache, then network (hashed filenames make this safe).
  if (request.destination === 'script' || request.destination === 'style' || request.destination === 'image') {
    event.respondWith(
      caches.match(request).then((hit) =>
        hit ?? fetch(request).then((resp) => {
          const copy = resp.clone()
          caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy))
          return resp
        }),
      ),
    )
  }
})
