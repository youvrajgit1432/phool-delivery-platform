// sw.js - Enhanced Service Worker with Firebase FCM Integration & Auto-Registration Support
const CACHE_NAME = 'phool-delivery-platform-v4.4.0';
const BASE_PATH = self.location.pathname.includes('/phool-delivery-platform/public_html')
    ? '/phool-delivery-platform/public_html'
    : '';

// Firebase Configuration
const firebaseConfig = {
    apiKey: "AIzaSyDexampleAPIkey123456789",
    projectId: "phooldelivery-888a6",
    messagingSenderId: "38144076822",
    appId: "your-web-app-id"
};

// Import Firebase scripts
importScripts('https://www.gstatic.com/firebasejs/9.6.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.6.0/firebase-messaging-compat.js');

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Background message handler (when app is in background)
messaging.onBackgroundMessage((payload) => {
    console.log('[SW] Received background message:', payload);

    const notificationTitle = payload.notification?.title || 'Phool Delivery';
    const notificationOptions = {
        body: payload.notification?.body || 'You have a new notification',
        icon: payload.notification?.icon || BASE_PATH + '/assets/img/favicon1.jpg',
        badge: BASE_PATH + '/assets/img/favicon1.jpg',
        data: payload.data || {},
        tag: payload.data?.tag || 'phool-delivery-platform',
        requireInteraction: true,
        vibrate: [200, 100, 200],
        actions: [
            {
                action: 'view',
                title: 'View Details'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };

    return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Enhanced Push Event Handler
self.addEventListener('push', function(event) {
    console.log('[SW] Push event received:', event);

    let data = {};
    try {
        if (event.data) {
            data = event.data.json();
        }
    } catch (e) {
        console.log('[SW] Push data is not JSON, using text:', event.data.text());
        data = { body: event.data.text() };
    }

    // Handle both Firebase and custom payloads
    let notificationData = data;
    if (data.notification) {
        // Firebase format
        notificationData = {
            ...data.notification,
            data: data.data || {}
        };
    }

    const title = notificationData.title || 'Phool Delivery';
    const options = {
        body: notificationData.body || notificationData.message || 'You have a new notification',
        icon: notificationData.icon || BASE_PATH + '/assets/img/favicon1.jpg',
        badge: BASE_PATH + '/assets/img/favicon1.jpg',
        image: notificationData.image,
        data: notificationData.data || notificationData,
        tag: notificationData.tag || 'phool-delivery-platform',
        requireInteraction: true,
        vibrate: [200, 100, 200],
        timestamp: notificationData.timestamp || Date.now(),
        actions: notificationData.actions || [
            {
                action: 'view',
                title: 'View Order'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
            .then(() => {
                console.log('[SW] Notification shown successfully');
                // Log delivery if we have notification ID
                if (notificationData.data?.notification_id) {
                    logNotificationDelivery(notificationData.data.notification_id, notificationData.data.device_id);
                }
            })
            .catch(error => {
                console.error('[SW] Error showing notification:', error);
                // Fallback for simple text payload
                const text = event.data.text();
                return self.registration.showNotification('Phool Delivery', {
                    body: text,
                    icon: BASE_PATH + '/assets/img/favicon1.jpg',
                    badge: BASE_PATH + '/assets/img/favicon1.jpg',
                    vibrate: [200, 100, 200],
                    requireInteraction: true
                });
            })
    );
});

// Enhanced Notification Click Handler
self.addEventListener('notificationclick', function(event) {
    console.log('[SW] Notification click received:', event);

    event.notification.close();

    const notificationData = event.notification.data || {};
    let targetUrl = notificationData.url || BASE_PATH + '/';

    // Handle different action buttons
    if (event.action === 'view') {
        if (notificationData.order_id) {
            targetUrl = BASE_PATH + '/account/orders?order_id=' + notificationData.order_id;
        } else if (notificationData.url) {
            targetUrl = notificationData.url;
        }
    } else if (event.action === 'dismiss') {
        return; // Just close the notification
    }

    // Mark as read in server
    if (notificationData.notification_id && notificationData.device_id) {
        fetch(BASE_PATH + '/api/notifications/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notification_id: notificationData.notification_id,
                device_id: notificationData.device_id,
                read_at: new Date().toISOString()
            })
        }).catch(error => console.error('[SW] Error marking notification as read:', error));
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(windowClients) {
                // Check if there's already a window/tab open with the target URL
                for (let i = 0; i < windowClients.length; i++) {
                    const client = windowClients[i];
                    const clientUrl = new URL(client.url);
                    const targetUrlObj = new URL(targetUrl, self.location.origin);

                    if (clientUrl.pathname === targetUrlObj.pathname && 'focus' in client) {
                        return client.focus();
                    }
                }

                // If no existing window, open a new one
                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            })
    );
});

// Enhanced Push Subscription Management
self.addEventListener('pushsubscriptionchange', function(event) {
    console.log('[SW] Push subscription changed:', event);

    event.waitUntil(
        self.registration.pushManager.subscribe(event.options)
            .then(function(subscription) {
                console.log('[SW] New subscription:', subscription);
                return fetch(BASE_PATH + '/api/notifications/update-subscription', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        old_endpoint: event.oldSubscription ? event.oldSubscription.endpoint : null,
                        new_subscription: subscription
                    })
                });
            })
            .catch(function(error) {
                console.error('[SW] Error updating push subscription:', error);
            })
    );
});

// Install event - cache core assets
self.addEventListener('install', (event) => {
  console.log('[SW] Installing with base path:', BASE_PATH);

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        const criticalAssets = [
          BASE_PATH + '/',
          BASE_PATH + '/index.php',
          BASE_PATH + '/offline.html',
          BASE_PATH + '/assets/css/main.css',
          BASE_PATH + '/assets/css/responsive.css',
          BASE_PATH + '/assets/css/pwa.css',
          BASE_PATH + '/assets/js/app.js',
          BASE_PATH + '/assets/js/pwa.js',
          BASE_PATH + '/assets/img/favicon.png',
          BASE_PATH + '/assets/img/logo.jpg',
          BASE_PATH + '/assets/img/products/placeholder.jpg',
          BASE_PATH + '/assets/img/favicon1.jpg',
          BASE_PATH + '/manifest.webmanifest'
        ].filter(url => url && url !== BASE_PATH + '/undefined' && !url.includes('undefined'));

        console.log('[SW] Caching app shell', criticalAssets);
        return cache.addAll(criticalAssets).catch(error => {
          console.log('[SW] Some files failed to cache:', error);
          return Promise.resolve();
        });
      })
      .then(() => {
        console.log('[SW] Skip waiting for activation');
        return self.skipWaiting();
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating...');

  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[SW] Deleting old cache', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('[SW] Claiming clients');
      return self.clients.claim();
    })
  );
});

// Fetch event - serve from cache or network with logout handling
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Skip caching for logout and authentication routes
  if (event.request.url.includes('/logout') ||
      event.request.url.includes('/login') ||
      event.request.url.includes('/verify-otp')) {
    return fetch(event.request);
  }

  // Skip non-GET requests and browser extensions
  if (request.method !== 'GET' ||
      url.protocol === 'chrome-extension:' ||
      url.hostname.includes('browser-sync') ||
      url.hostname.includes('fcm.googleapis.com') ||
      url.hostname.includes('googleapis.com')) {
    return;
  }

  // Don't cache dynamic pages and API endpoints
  if (url.pathname.includes('/cart/') ||
      url.pathname.includes('/api/') ||
      url.pathname.includes('/admin/') ||
      url.search.includes('nocache=true') ||
      request.headers.get('X-Requested-With') === 'XMLHttpRequest') {

    event.respondWith(
      fetch(request)
        .then(response => {
          return response;
        })
        .catch(error => {
          if (url.pathname.includes('/api/')) {
            return new Response(JSON.stringify({
              error: 'Network unavailable',
              offline: true
            }), {
              status: 408,
              headers: { 'Content-Type': 'application/json' }
            });
          }
          throw error;
        })
    );
    return;
  }

  // Handle different resource types
  if (url.pathname.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
    event.respondWith(handleImageRequest(request));
    return;
  }

  if (url.pathname.match(/\.(css|js)$/)) {
    event.respondWith(handleAssetRequest(request));
    return;
  }

  if (request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(handleHtmlRequest(request));
    return;
  }

  event.respondWith(handleDefaultRequest(request));
});

// Request handlers
async function handleImageRequest(request) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) return cachedResponse;

    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    const placeholderPaths = [
      BASE_PATH + '/assets/img/products/placeholder.jpg',
      '/assets/img/products/placeholder.jpg'
    ];

    for (const path of placeholderPaths) {
      const placeholder = await caches.match(path);
      if (placeholder) return placeholder;
    }

    return new Response('Image not available', {
      status: 404,
      headers: { 'Content-Type': 'text/plain' }
    });
  }
}

async function handleAssetRequest(request) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      updateCache(request);
      return cachedResponse;
    }
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    return new Response('Asset not available', {
      status: 404,
      headers: { 'Content-Type': 'text/plain' }
    });
  }
}

async function handleHtmlRequest(request) {
  try {
    // Navigation requests carry redirect:'manual'. Re-issuing them with that
    // mode turns any server redirect (e.g. to the login page when the session
    // has expired) into an opaque response, which we would wrongly treat as a
    // network failure and replace with the offline page. Follow redirects here
    // so protected pages correctly fall through to the login screen.
    const networkResponse = await fetch(request, { redirect: 'follow' });
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
      return networkResponse;
    }
    throw new Error('Network response not ok: status=' + networkResponse.status);
  } catch (error) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) return cachedResponse;

    const fallbackOptions = [
      BASE_PATH + '/offline.html',
      '/offline.html',
      BASE_PATH + '/index.php'
    ];

    for (const fallback of fallbackOptions) {
      const fallbackResponse = await caches.match(fallback);
      if (fallbackResponse) return fallbackResponse;
    }

    return new Response('You are offline', {
      status: 408,
      headers: { 'Content-Type': 'text/html' }
    });
  }
}

async function handleDefaultRequest(request) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      updateCache(request);
      return cachedResponse;
    }
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    return new Response('Resource not available', {
      status: 404,
      headers: { 'Content-Type': 'text/plain' }
    });
  }
}

async function updateCache(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, response);
    }
  } catch (error) {
    console.log('[SW] Background cache update failed:', error);
  }
}

// Enhanced message handling with auto-registration support
self.addEventListener('message', (event) => {
    console.log('[SW] Received message', event.data);

    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({
            version: CACHE_NAME,
            basePath: BASE_PATH
        });
    }

    // Handle automatic push subscription registration
    if (event.data && event.data.type === 'AUTO_REGISTER_PUSH') {
        event.waitUntil(handleAutomaticPushRegistration(event.data));
    }

    // Enhanced: Handle logout by clearing all caches
    if (event.data && event.data.type === 'LOGOUT') {
        event.waitUntil(
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        return caches.delete(cacheName);
                    })
                );
            })
        );
    }

    // Handle auto-enable notifications
    if (event.data && event.data.type === 'AUTO_ENABLE_NOTIFICATIONS') {
        event.waitUntil(handleAutoEnableNotifications(event.data));
    }
});

// Auto-enable notifications handler
async function handleAutoEnableNotifications(data) {
    try {
        console.log('[SW] Auto-enabling notifications');

        // Notify all clients that notifications are being auto-enabled
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'NOTIFICATIONS_AUTO_ENABLED',
                data: data
            });
        });
    } catch (error) {
        console.error('[SW] Auto-enable notifications failed:', error);
    }
}

// Automatic Push Registration Handler
async function handleAutomaticPushRegistration(data) {
  try {
    console.log('[SW] Starting automatic push registration');

    // Get VAPID public key from server
    const vapidResponse = await fetch(BASE_PATH + '/api/notifications/vapid-key');
    const vapidData = await vapidResponse.json();

    if (!vapidData.success || !vapidData.publicKey) {
      throw new Error('Failed to get VAPID key');
    }

    const applicationServerKey = urlBase64ToUint8Array(vapidData.publicKey);
    const registration = await self.registration;

    // Subscribe to push
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: applicationServerKey
    });

    console.log('[SW] Automatic push subscription created:', subscription);

    // Register device with server
    const subscriptionJSON = subscription.toJSON();
    const deviceData = {
      device_token: subscriptionJSON.endpoint,
      subscription_data: subscriptionJSON,
      device_type: 'web',
      browser_name: data.browserName || 'Unknown',
      browser_version: data.browserVersion || 'Unknown',
      platform: data.platform || navigator.platform,
      user_agent: navigator.userAgent,
      auto_registered: true
    };

    const registerResponse = await fetch(BASE_PATH + '/api/notifications/register-device', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(deviceData)
    });

    const result = await registerResponse.json();

    if (result.success) {
      console.log('[SW] Automatic push registration successful');
      // Notify all clients
      const clients = await self.clients.matchAll();
      clients.forEach(client => {
        client.postMessage({
          type: 'PUSH_AUTO_REGISTRATION_SUCCESS',
          data: result
        });
      });
    } else {
      throw new Error(result.message || 'Registration failed');
    }
  } catch (error) {
    console.error('[SW] Automatic push registration failed:', error);
    // Notify clients of failure
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
      client.postMessage({
        type: 'PUSH_AUTO_REGISTRATION_FAILED',
        error: error.message
      });
    });
  }
}

// Utility function for VAPID key conversion
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');

  const rawData = self.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

// Log notification delivery
async function logNotificationDelivery(notificationId, deviceId) {
  if (!notificationId || !deviceId) return;

  try {
    await fetch(BASE_PATH + '/api/notifications/log-delivery', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        notification_id: notificationId,
        device_id: deviceId,
        delivered_at: new Date().toISOString()
      })
    });
    console.log('[SW] Delivery logged for notification:', notificationId);
  } catch (error) {
    console.error('[SW] Error logging delivery:', error);
  }
}