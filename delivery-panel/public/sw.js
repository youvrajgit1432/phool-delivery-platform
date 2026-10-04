// Delivery Panel Service Worker - Enhanced with Firebase FCM Integration & Auto-Registration Support
const CACHE_NAME = 'phool-delivery-rider-v1.0.0';
const BASE_PATH = '/delivery-panel/public';

// Firebase Configuration for Riders
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

// Background message handler for new orders and assignments
messaging.onBackgroundMessage((payload) => {
    console.log('[SW] Received background message:', payload);
    
    const notificationTitle = payload.notification?.title || 'Phool Delivery - Rider Panel';
    const notificationOptions = {
        body: payload.notification?.body || 'You have a new notification',
        icon: payload.notification?.icon || BASE_PATH + '/assets/img/icon-rider.png',
        badge: BASE_PATH + '/assets/img/icon-rider.png',
        data: payload.data || {},
        tag: payload.data?.tag || 'phool-delivery-rider',
        requireInteraction: payload.data?.requireInteraction || true,
        vibrate: [200, 100, 200],
        actions: [
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

    return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Enhanced Push Event Handler - Handle order assignments and alerts
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
        notificationData = {
            ...data.notification,
            data: data.data || {}
        };
    }

    const title = notificationData.title || 'Phool Delivery';
    const options = {
        body: notificationData.body || notificationData.message || 'You have a new notification',
        icon: notificationData.icon || BASE_PATH + '/assets/img/icon-rider.png',
        badge: BASE_PATH + '/assets/img/icon-rider.png',
        image: notificationData.image,
        data: notificationData.data || notificationData,
        tag: notificationData.tag || 'phool-delivery-rider',
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
                // Log delivery
                if (notificationData.data?.notification_id) {
                    logNotificationDelivery(notificationData.data.notification_id, notificationData.data.device_id);
                }
            })
            .catch(error => {
                console.error('[SW] Error showing notification:', error);
                const text = event.data.text();
                return self.registration.showNotification('Phool Delivery', {
                    body: text,
                    icon: BASE_PATH + '/assets/img/icon-rider.png',
                    badge: BASE_PATH + '/assets/img/icon-rider.png',
                    vibrate: [200, 100, 200],
                    requireInteraction: true
                });
            })
    );
});

// Enhanced Notification Click Handler - Route to appropriate pages
self.addEventListener('notificationclick', function(event) {
    console.log('[SW] Notification click received:', event);
    
    event.notification.close();

    const notificationData = event.notification.data || {};
    let targetUrl = BASE_PATH + '/';

    // Handle different action buttons
    if (event.action === 'view') {
        if (notificationData.order_id) {
            targetUrl = BASE_PATH + '/?view=order&id=' + notificationData.order_id;
        } else {
            targetUrl = BASE_PATH + '/?page=orders';
        }
    } else if (event.action === 'dismiss') {
        return;
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
                // Check if there's already a window/tab open
                for (let i = 0; i < windowClients.length; i++) {
                    const client = windowClients[i];
                    if (client.url === targetUrl && 'focus' in client) {
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

// Install event - cache core assets for delivery panel
self.addEventListener('install', (event) => {
  console.log('[SW] Installing with base path:', BASE_PATH);
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        const criticalAssets = [
          BASE_PATH + '/',
          BASE_PATH + '/index.php',
          BASE_PATH + '/offline.html',
          BASE_PATH + '/assets/css/style.css',
          BASE_PATH + '/assets/css/responsive.css',
          BASE_PATH + '/assets/css/pwa.css',
          BASE_PATH + '/assets/js/app.js',
          BASE_PATH + '/assets/js/pwa.js',
          BASE_PATH + '/assets/img/icon-rider.png',
          BASE_PATH + '/assets/img/logo.png',
          BASE_PATH + '/manifest.webmanifest'
        ].filter(url => url && url !== BASE_PATH + '/undefined' && !url.includes('undefined'));
        
        console.log('[SW] Caching app shell', criticalAssets);
        return cache.addAll(criticalAssets).catch(error => {
            console.error('[SW] Cache addAll error:', error);
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
                    console.log('[SW] Deleting old cache:', cacheName);
                    return caches.delete(cacheName);
                }
            })
        );
    }).then(() => {
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
      return event.respondWith(fetch(event.request));
  }
  
  // Skip non-GET requests and browser extensions
  if (request.method !== 'GET' || 
      url.protocol === 'chrome-extension:' ||
      url.hostname.includes('browser-sync') ||
      url.hostname.includes('fcm.googleapis.com') ||
      url.hostname.includes('googleapis.com')) {
      return event.respondWith(fetch(event.request));
  }

  // Don't cache dynamic pages and API endpoints
  if (url.pathname.includes('/ajax/') || 
      url.pathname.includes('/api/') || 
      url.search.includes('nocache=true') ||
      request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
      return event.respondWith(
          fetch(event.request).then(response => {
              if (response && response.status === 200 && response.type !== 'error') {
                  updateCache(event.request);
              }
              return response;
          }).catch(error => {
              console.error('[SW] Fetch error for:', request.url, error);
              // Return cached response for offline support
              return caches.match(request);
          })
      );
  }

  // Handle different resource types
  if (url.pathname.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
      return event.respondWith(handleImageRequest(request));
  }

  if (url.pathname.match(/\.(css|js)$/)) {
      return event.respondWith(handleAssetRequest(request));
  }

  if (request.headers.get('accept')?.includes('text/html')) {
      return event.respondWith(handleHtmlRequest(request));
  }

  event.respondWith(handleDefaultRequest(request));
});

// Request handlers
async function handleImageRequest(request) {
  try {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response && response.status === 200) {
        cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.error('[SW] Image request error:', error);
    return caches.match(BASE_PATH + '/assets/img/placeholder.png')
        .then(response => response || new Response('Image not available', { status: 404 }));
  }
}

async function handleAssetRequest(request) {
  try {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response && response.status === 200) {
        cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.error('[SW] Asset request error:', error);
    return new Response('Asset not available', { status: 404 });
  }
}

async function handleHtmlRequest(request) {
  try {
    const response = await fetch(request);
    if (response && response.status === 200) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.error('[SW] HTML request error:', error);
    return caches.match(request)
        .then(cached => cached || caches.match(BASE_PATH + '/offline.html'));
  }
}

async function handleDefaultRequest(request) {
  try {
    const response = await fetch(request);
    if (response && response.status === 200 && response.type !== 'error') {
        updateCache(request);
    }
    return response;
  } catch (error) {
    console.error('[SW] Fetch error:', error);
    return caches.match(request)
        .catch(() => new Response('Offline', { status: 503 }));
  }
}

async function updateCache(request) {
  try {
    const response = await fetch(request);
    const cache = await caches.open(CACHE_NAME);
    cache.put(request, response);
  } catch (error) {
    console.error('[SW] Cache update error:', error);
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
            timestamp: new Date().toISOString()
        });
    }
  
    // Handle automatic push subscription registration
    if (event.data && event.data.type === 'AUTO_REGISTER_PUSH') {
        handleAutomaticPushRegistration(event.data);
    }
  
    // Handle logout by clearing all caches
    if (event.data && event.data.type === 'LOGOUT') {
        caches.keys().then(names => {
            names.forEach(name => caches.delete(name));
        });
    }
  
    // Handle auto-enable notifications
    if (event.data && event.data.type === 'AUTO_ENABLE_NOTIFICATIONS') {
        handleAutoEnableNotifications(event.data);
    }
});

// Auto-enable notifications handler
async function handleAutoEnableNotifications(data) {
    try {
        const permission = await Notification.requestPermission();
        console.log('[SW] Notification permission:', permission);
        
        if (permission === 'granted') {
            console.log('[SW] Notifications auto-enabled');
            sessionStorage.setItem('notifications_auto_enabled', 'true');
        }
    } catch (error) {
        console.error('[SW] Error auto-enabling notifications:', error);
    }
}

// Automatic Push Registration Handler
async function handleAutomaticPushRegistration(data) {
  try {
    if (!('PushManager' in window)) {
        console.log('[SW] PushManager not supported');
        return;
    }

    const subscription = await self.registration.pushManager.getSubscription();
    
    if (!subscription) {
        console.log('[SW] No existing subscription, creating new one');
        
        // VAPID public key for your app
        const vapidPublicKey = data.vapidPublicKey || 'YOUR_VAPID_PUBLIC_KEY';
        
        try {
            const newSubscription = await self.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
            });
            
            console.log('[SW] New push subscription created:', newSubscription);
            await registerPushSubscriptionWithServer(newSubscription);
            sessionStorage.setItem('push_notification_enabled', 'true');
        } catch (error) {
            console.error('[SW] Error creating subscription:', error);
        }
    } else {
        console.log('[SW] Existing subscription found:', subscription);
        await registerPushSubscriptionWithServer(subscription);
    }
  } catch (error) {
    console.error('[SW] Error in automatic push registration:', error);
  }
}

// Register subscription with backend
async function registerPushSubscriptionWithServer(subscription) {
    if (!subscription) return;

    try {
        const response = await fetch(BASE_PATH + '/api/notifications/register-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                subscription: subscription,
                device_type: 'rider_app',
                timestamp: new Date().toISOString()
            })
        });

        const result = await response.json();
        if (result.success) {
            console.log('[SW] Push subscription registered with server');
            sessionStorage.setItem('push_registered', 'true');
        } else {
            console.error('[SW] Failed to register subscription:', result.message);
        }
    } catch (error) {
        console.error('[SW] Error registering subscription with server:', error);
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
            delivered_at: new Date().toISOString(),
            delivered_via: 'service_worker'
        })
    });
  } catch (error) {
    console.error('[SW] Error logging notification delivery:', error);
  }
}
