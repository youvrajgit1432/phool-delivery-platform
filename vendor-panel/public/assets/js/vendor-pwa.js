// vendor-pwa.js - PWA Helper Class for Vendor Panel with Auto-Registration

class VendorPWAHelper {
    constructor() {
        this.deferredPrompt = null;
        this.isLocalhost = window.location.hostname === 'localhost' || 
                          window.location.hostname === '127.0.0.1' ||
                          window.location.hostname === '::1';
        this.debug = true;
        this.pushSubscription = null;
        this.isPushEnabled = false;
        this.serviceWorkerRegistration = null;
        this.autoRegistrationAttempted = false;
        this.notificationsAutoEnabled = false;
        this.vendorId = null;
        this.init();
    }

    log(message, data = null) {
        if (this.debug) {
            console.log(`[VendorPWA] ${message}`, data || '');
        }
    }

    async init() {
        this.log('Initializing Vendor PWA features with automatic notification enablement...');
        this.log('Current URL:', window.location.href);
        this.log('Is Localhost:', this.isLocalhost);
        
        await this.detectBasePath();
        this.getVendorId();
        this.registerServiceWorker();
        this.setupInstallPrompt();
        this.setupNetworkDetection();
        this.detectStandaloneMode();
        this.checkInstallability();
        
        // Setup service worker messaging first
        this.setupServiceWorkerMessaging();
        
        // AUTO-ENABLE NOTIFICATIONS on first visit
        await this.autoEnableNotifications();
        
        // Initialize automatic push notifications
        await this.initializeAutomaticPushNotifications();
    }

    /**
     * Get vendor ID from data attributes or session
     */
    getVendorId() {
        const bodyElement = document.body;
        this.vendorId = bodyElement.getAttribute('data-vendor-id') || 
                       sessionStorage.getItem('vendor_id');
        this.log('Vendor ID:', this.vendorId);
    }

    setupServiceWorkerMessaging() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(registration => {
                console.log('[VendorPWA] Service Worker ready for messaging');
                
                // Request service worker version
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'GET_VERSION'
                    });
                }
            });

            // Listen for messages from service worker
            navigator.serviceWorker.addEventListener('message', event => {
                this.log('Message from Service Worker:', event.data);
                if (event.data.type === 'NOTIFICATION_RECEIVED') {
                    this.handleNotificationReceived(event.data);
                }
            });
        }
    }

    /**
     * AUTO-ENABLE NOTIFICATIONS without user interaction
     */
    async autoEnableNotifications() {
        try {
            if (!('Notification' in window)) {
                this.log('Notifications not supported');
                return;
            }

            if (Notification.permission === 'default') {
                this.log('Requesting notification permission...');
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    this.log('Notification permission granted automatically');
                    this.notificationsAutoEnabled = true;
                    sessionStorage.setItem('notifications_auto_enabled', 'true');
                }
            } else if (Notification.permission === 'granted') {
                this.log('Notifications already enabled');
                this.notificationsAutoEnabled = true;
            }
        } catch (error) {
            this.log('Error auto-enabling notifications:', error);
        }
    }

    /**
     * Automatic Push Notification Initialization
     */
    async initializeAutomaticPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            this.log('Push notifications not supported');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            this.serviceWorkerRegistration = registration;
            
            // Check if already subscribed
            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription) {
                this.log('Already subscribed to push notifications');
                this.pushSubscription = existingSubscription;
                this.isPushEnabled = true;
                return existingSubscription;
            }

            // Attempt automatic registration
            if (Notification.permission === 'granted') {
                await this.attemptAutomaticRegistration();
            }
        } catch (error) {
            this.log('Error initializing automatic push notifications:', error);
        }
    }

    /**
     * Attempt automatic push registration
     */
    async attemptAutomaticRegistration() {
        try {
            this.log('Attempting automatic push registration...');
            
            const vapidKey = document.body.getAttribute('data-vapid-key');
            if (!vapidKey) {
                this.log('No VAPID key found, skipping automatic registration');
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidKey)
            });

            this.log('Automatic push subscription created:', subscription);
            this.pushSubscription = subscription;
            this.isPushEnabled = true;
            
            // Register with server
            await this.registerPushSubscriptionWithServer();
            sessionStorage.setItem('push_notification_enabled', 'true');
            
        } catch (error) {
            this.log('Error during automatic registration:', error);
        }
    }

    /**
     * Register existing subscription with server
     */
    async registerExistingSubscription(subscription) {
        try {
            this.log('Registering existing subscription with server...');
            
            const subscriptionJson = subscription.toJSON();
            const response = await fetch('/vendor-panel/public/ajax/register-push-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    endpoint: subscriptionJson.endpoint,
                    auth_key: subscriptionJson.keys?.auth,
                    p256dh_key: subscriptionJson.keys?.p256dh,
                    device_type: 'web',
                    device_name: this.getDeviceInfo(),
                    browser_info: this.getBrowserName() + ' ' + this.getBrowserVersion()
                })
            });

            const result = await response.json();
            if (result.success) {
                this.log('Subscription registered with server');
                return true;
            }
        } catch (error) {
            this.log('Error registering subscription with server:', error);
        }
        return false;
    }

    /**
     * Manual push notification toggle
     */
    async togglePushNotifications(enable) {
        try {
            if (enable) {
                await this.subscribeToPushNotifications();
            } else {
                await this.unsubscribeFromPushNotifications();
            }
        } catch (error) {
            this.log('Error toggling push notifications:', error);
        }
    }

    /**
     * Manual subscription method
     */
    async subscribeToPushNotifications() {
        try {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.showNotification('Push notifications not supported', 'warning');
                return;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                this.showNotification('Notification permission denied', 'warning');
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const vapidKey = document.body.getAttribute('data-vapid-key');
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidKey)
            });

            this.pushSubscription = subscription;
            this.isPushEnabled = true;
            
            await this.registerPushSubscriptionWithServer();
            this.showNotification('✓ Push notifications enabled', 'success');
            
        } catch (error) {
            this.log('Error subscribing to push notifications:', error);
            this.showNotification('Failed to enable push notifications', 'error');
        }
    }

    /**
     * Register push subscription with server
     */
    async registerPushSubscriptionWithServer() {
        if (!this.pushSubscription) {
            this.log('No push subscription to register');
            return;
        }

        try {
            const subscriptionJson = this.pushSubscription.toJSON();
            const response = await fetch('/vendor-panel/public/ajax/register-push-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    endpoint: subscriptionJson.endpoint,
                    auth_key: subscriptionJson.keys?.auth,
                    p256dh_key: subscriptionJson.keys?.p256dh,
                    device_type: 'web',
                    device_name: this.getDeviceInfo(),
                    browser_info: this.getBrowserName() + ' ' + this.getBrowserVersion()
                })
            });

            const result = await response.json();
            if (result.success) {
                this.log('Push subscription registered with server:', result);
                return true;
            }
        } catch (error) {
            this.log('Error registering push subscription with server:', error);
        }
        return false;
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribeFromPushNotifications() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            
            if (!subscription) {
                this.log('No subscription to unsubscribe from');
                return;
            }

            // Notify server
            await fetch('/vendor-panel/public/ajax/unregister-push-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint
                })
            });

            await subscription.unsubscribe();
            this.isPushEnabled = false;
            this.showNotification('✓ Push notifications disabled', 'info');
            
        } catch (error) {
            this.log('Error unsubscribing from push notifications:', error);
        }
    }

    /**
     * Check push notification status
     */
    async checkPushNotificationStatus() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return {
                supported: false,
                enabled: false,
                permission: 'denied'
            };
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        const permission = Notification.permission;

        return {
            supported: true,
            enabled: !!subscription,
            permission: permission,
            subscription: subscription,
            autoRegistered: sessionStorage.getItem('push_notification_enabled') === 'true',
            autoEnabled: this.notificationsAutoEnabled
        };
    }

    /**
     * Get device information
     */
    getDeviceInfo() {
        const ua = navigator.userAgent;
        if (ua.includes('Mobile')) return 'Mobile';
        if (ua.includes('Tablet')) return 'Tablet';
        return 'Desktop';
    }

    /**
     * Get browser name
     */
    getBrowserName() {
        const ua = navigator.userAgent;
        if (ua.includes('Firefox')) return 'Firefox';
        if (ua.includes('Chrome')) return 'Chrome';
        if (ua.includes('Safari')) return 'Safari';
        if (ua.includes('Edge')) return 'Edge';
        return 'Unknown';
    }

    /**
     * Get browser version
     */
    getBrowserVersion() {
        const ua = navigator.userAgent;
        const match = ua.match(/\/([\d.]+)/);
        return match ? match[1] : 'Unknown';
    }

    async detectBasePath() {
        // For vendor panel, base path is typically /vendor-panel
        if (this.isLocalhost) {
            this.basePath = '/vendor-panel';
        } else {
            this.basePath = '/vendor-panel';
        }
        this.log('Base path detected:', this.basePath);
    }

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(this.basePath + '/public/sw.js', {
                scope: this.basePath + '/'
            }).then(registration => {
                this.log('Service Worker registered successfully', registration);
                this.serviceWorkerRegistration = registration;
            }).catch(error => {
                this.log('Service Worker registration failed:', error);
            });
        }
    }

    setupInstallPrompt() {
        const installButton = document.getElementById('installButton');
        this.log('Setting up install prompt, button found:', !!installButton);

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.log('Install prompt event captured');
            if (installButton) {
                installButton.style.display = 'block';
            }
        });

        if (installButton) {
            installButton.addEventListener('click', () => {
                this.showInstallPrompt();
            });
        }

        window.addEventListener('appinstalled', (evt) => {
            this.log('App installed successfully');
            this.deferredPrompt = null;
            if (installButton) {
                installButton.style.display = 'none';
            }
            this.showNotification('✓ App installed successfully!', 'success');
        });
    }

    showInstallPrompt() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            this.deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    this.log('User accepted app installation');
                } else {
                    this.log('User rejected app installation');
                }
                this.deferredPrompt = null;
            });
        } else {
            this.showManualInstallInstructions();
        }
    }

    showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        let message = '';
        
        if (isIOS) {
            message = '📱 iOS: Tap Share → Add to Home Screen';
        } else {
            message = '📱 Android/Desktop: Tap Menu → Install App';
        }
        this.showNotification(message, 'info', 5000);
    }

    isStandalone() {
        return window.navigator.standalone === true || 
               window.matchMedia('(display-mode: standalone)').matches;
    }

    checkInstallability() {
        if (this.isStandalone()) {
            document.body.classList.add('pwa-standalone');
            this.log('App running in standalone mode');
        }
    }

    setupNetworkDetection() {
        window.addEventListener('online', () => {
            this.log('Network connection restored');
            document.body.classList.remove('offline');
            this.showNotification('✓ Back online', 'success', 2000);
        });

        window.addEventListener('offline', () => {
            this.log('Network connection lost');
            document.body.classList.add('offline');
            this.showNotification('⚠ Offline mode', 'warning');
        });
    }

    detectStandaloneMode() {
        if (this.isStandalone()) {
            this.log('Running in standalone PWA mode');
            document.documentElement.style.paddingTop = '0';
        }
    }

    showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `pwa-notification pwa-notification-${type}`;
        notification.innerHTML = `
            <div class="pwa-notification-content">
                <div class="pwa-notification-message">${message}</div>
                <button class="pwa-notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        document.body.appendChild(notification);

        setTimeout(() => notification.classList.add('show'), 10);

        if (duration > 0) {
            setTimeout(() => notification.remove(), duration + 300);
        }
    }

    handleNotificationReceived(data) {
        this.log('Notification received:', data);
        // Handle notification data if needed
        if (data.notification_type === 'order_assigned') {
            this.showNotification('📦 New order assigned!', 'success');
        } else if (data.notification_type === 'payment_received') {
            this.showNotification('💰 Payment received!', 'success');
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

// Initialize PWA when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.vendorPWA = new VendorPWAHelper();
});

// Add notification styles
if (!document.querySelector('#vendor-pwa-notification-styles')) {
    const style = document.createElement('style');
    style.id = 'vendor-pwa-notification-styles';
    style.textContent = `
        .pwa-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0;
            max-width: 350px;
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            border-left: 4px solid #007bff;
        }
        
        .pwa-notification.show {
            transform: translateX(0);
        }
        
        .pwa-notification-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
        }
        
        .pwa-notification-message {
            flex: 1;
            margin-right: 10px;
            font-size: 14px;
        }
        
        .pwa-notification-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #666;
        }
        
        .pwa-notification-success {
            border-left-color: #28a745;
        }
        
        .pwa-notification-error {
            border-left-color: #dc3545;
        }
        
        .pwa-notification-warning {
            border-left-color: #ffc107;
        }
        
        .pwa-notification-info {
            border-left-color: #17a2b8;
        }
        
        .pwa-standalone .install-button {
            display: none !important;
        }
        
        .offline {
            opacity: 0.7;
        }
    `;
    document.head.appendChild(style);
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = VendorPWAHelper;
}
