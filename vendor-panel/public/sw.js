// sw.js - Service Worker for Vendor Panel with Firebase FCM Integration & Auto-Registration Support
const CACHE_NAME = 'phool-vendor-panel-v1.0.0';
const BASE_PATH = self.location.pathname.includes('/vendor-panel') 
    ? '/vendor-panel' 
    : '';

// Firebase Configuration for Vendor Panel
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
    
    const notificationTitle = payload.notification?.title || 'Phool Vendor Panel';
    const notificationOptions = {
        body: payload.notification?.body || 'You have a new notification',
        icon: payload.notification?.icon || BASE_PATH + '/assets/img/logo.png',
        badge: BASE_PATH + '/assets/img/logo.png',
        data: payload.data || {},
        tag: payload.data?.tag || 'vendor-panel',
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
        notificationData = {
            ...data.notification,
            data: data.data || {}
        };
    }

    const title = notificationData.title || 'Phool Vendor Panel';
    const options = {
        body: notificationData.body || notificationData.message || 'You have a new notification',
        icon: notificationData.icon || BASE_PATH + '/assets/img/logo.png',
        badge: BASE_PATH + '/assets/img/logo.png',
        image: notificationData.image,
        data: notificationData.data || notificationData,
        tag: notificationData.tag || 'vendor-panel',
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
            })
            .catch(error => {
                console.error('[SW] Error showing notification:', error);
                const text = event.data.text();
                return self.registration.showNotification('Phool Vendor Panel', {
                    body: text,
                    icon: BASE_PATH + '/assets/img/logo.png',
                    badge: BASE_PATH + '/assets/img/logo.png',
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
            targetUrl = BASE_PATH + '/app/views/orders/view.php?id=' + notificationData.order_id;
        }
    } else if (event.action === 'dismiss') {
        return;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(windowClients) {
                for (let i = 0; i < windowClients.length; i++) {
                    if (windowClients[i].url === targetUrl && 'focus' in windowClients[i]) {
                        return windowClients[i].focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            })
    );
});

// Install event - cache core assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing with base path:', BASE_PATH);
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching essential assets');
                return cache.addAll([
                    BASE_PATH + '/',
                    BASE_PATH + '/public/index.php',
                    BASE_PATH + '/public/login.php',
                    BASE_PATH + '/public/offline.html',
                    BASE_PATH + '/public/manifest.webmanifest',
                    BASE_PATH + '/public/assets/css/',
                    BASE_PATH + '/public/assets/js/',
                    BASE_PATH + '/public/assets/img/'
                ]).catch(err => {
                    console.warn('[SW] Cache addAll error (some assets may not be available):', err);
                });
            })
            .then(() => {
                console.log('[SW] Installation complete');
                self.skipWaiting();
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
            console.log('[SW] Claiming clients...');
            return self.clients.claim();
        })
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Skip caching for logout and authentication routes
    if (event.request.url.includes('/logout') || 
        event.request.url.includes('/login') ||
        event.request.url.includes('/verify-otp')) {
        return event.respondWith(fetch(request));
    }
    
    // Skip non-GET requests and browser extensions
    if (request.method !== 'GET' || 
        url.protocol === 'chrome-extension:' ||
        url.hostname.includes('browser-sync') ||
        url.hostname.includes('fcm.googleapis.com') ||
        url.hostname.includes('googleapis.com')) {
        return event.respondWith(fetch(request));
    }

    // Don't cache dynamic pages and API endpoints
    if (url.pathname.includes('/ajax/') || 
        url.pathname.includes('/api/') || 
        url.search.includes('nocache=true') ||
        request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
        return event.respondWith(
            fetch(request)
                .then(response => {
                    return response;
                })
                .catch(error => {
                    return new Response('Offline - Dynamic content not available', {
                        status: 503,
                        statusText: 'Service Unavailable'
                    });
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
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
            return response;
        }
        return caches.match(request);
    } catch (error) {
        console.log('[SW] Image fetch error:', error);
        return caches.match(request).catch(() => {
            return new Response('Image not available', { status: 404 });
        });
    }
}

async function handleAssetRequest(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
            return response;
        }
        return caches.match(request);
    } catch (error) {
        console.log('[SW] Asset fetch error:', error);
        return caches.match(request);
    }
}

async function handleHtmlRequest(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
            return response;
        }
        return caches.match(request);
    } catch (error) {
        console.log('[SW] HTML fetch error:', error);
        const offlineUrl = BASE_PATH + '/public/offline.html';
        return caches.match(offlineUrl).catch(() => {
            return new Response('Offline', { status: 503 });
        });
    }
}

async function handleDefaultRequest(request) {
    try {
        return await fetch(request);
    } catch (error) {
        console.log('[SW] Default fetch error:', error);
        return caches.match(request).catch(() => {
            return new Response('Network error', { status: 503 });
        });
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
        handleAutomaticPushRegistration(event.data);
    }

    // Enhanced: Handle logout by clearing all caches
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
        const registration = await self.registration;
        if (registration && Notification.permission === 'granted') {
            console.log('[SW] Notifications already enabled');
        }
    } catch (error) {
        console.error('[SW] Error enabling notifications:', error);
    }
}

// Automatic Push Registration Handler
async function handleAutomaticPushRegistration(data) {
    try {
        console.log('[SW] Handling automatic push registration', data);
        
        if (!self.registration || !self.registration.pushManager) {
            console.warn('[SW] Push manager not available');
            return;
        }

        const subscription = await self.registration.pushManager.getSubscription();
        
        if (!subscription && data.vapidKey) {
            console.log('[SW] No existing subscription, creating new one');
            const newSubscription = await self.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(data.vapidKey)
            });

            console.log('[SW] Subscription created:', newSubscription);
            return newSubscription;
        } else {
            console.log('[SW] Subscription already exists or no VAPID key provided');
            return subscription;
        }
    } catch (error) {
        console.error('[SW] Error in automatic push registration:', error);
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

console.log('[SW] Service Worker loaded for Vendor Panel');
