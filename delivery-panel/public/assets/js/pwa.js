// Delivery Panel PWA Helper - Enhanced with Automatic Push Registration & Auto-enable
// Specialized for Rider Application

class RiderPWAHelper {
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
        this.riderId = null;
        this.init();
    }

    log(message, data = null) {
        if (this.debug) {
            console.log(`[RiderPWA] ${message}`, data || '');
        }
    }

    async init() {
        this.log('Initializing Rider PWA features with automatic notification enablement...');
        this.log('Current URL:', window.location.href);
        this.log('Is Localhost:', this.isLocalhost);
        
        // Get rider ID from session/session storage
        this.riderId = this.getRiderId();
        this.log('Rider ID:', this.riderId);
        
        // Fix URLs if needed
        if (!this.isLocalhost) {
            this.fixIncorrectURL();
        }
        
        await this.detectBasePath();
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
        
        // Setup order notification listeners
        this.setupOrderNotificationListeners();
    }

    /**
     * Get Rider ID from session storage or data attribute
     */
    getRiderId() {
        // Check data attribute on body
        const riderId = document.body.getAttribute('data-rider-id');
        if (riderId) return riderId;
        
        // Check session storage
        return sessionStorage.getItem('rider_id') || null;
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

            // Check if already granted
            if (Notification.permission === 'granted') {
                this.log('Notifications already granted');
                this.notificationsAutoEnabled = true;
                sessionStorage.setItem('notifications_auto_enabled', 'true');
                return;
            }

            // Check if already denied
            if (Notification.permission === 'denied') {
                this.log('Notifications denied by user');
                return;
            }

            // Request permission
            this.log('Requesting notification permission...');
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                this.log('Notifications permission granted');
                this.notificationsAutoEnabled = true;
                sessionStorage.setItem('notifications_auto_enabled', 'true');
            } else {
                this.log('Notifications permission:', permission);
            }
        } catch (error) {
            this.log('Error auto-enabling notifications:', error);
        }
    }

    setupServiceWorkerMessaging() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then((registration) => {
                this.log('Service Worker ready');
                
                // Send auto-enable signal
                if (registration.active) {
                    registration.active.postMessage({
                        type: 'AUTO_ENABLE_NOTIFICATIONS'
                    });
                }
            }).catch(error => {
                this.log('Service Worker ready error:', error);
            });
        }
    }

    /**
     * Automatic Push Notification Initialization for Riders
     * Registers for push notifications automatically on first visit
     */
    async initializeAutomaticPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            this.log('Push notifications not supported');
            return;
        }

        try {
            // Check if already registered
            const isAlreadyEnabled = sessionStorage.getItem('push_notification_enabled') === 'true';
            if (isAlreadyEnabled && !this.autoRegistrationAttempted) {
                this.log('Push notifications already enabled');
                return;
            }

            // Attempt automatic registration
            if (!this.autoRegistrationAttempted) {
                this.autoRegistrationAttempted = true;
                await this.attemptAutomaticRegistration();
            }
        } catch (error) {
            this.log('Error initializing push notifications:', error);
        }
    }

    /**
     * Attempt automatic push registration
     */
    async attemptAutomaticRegistration() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            // Check existing subscription
            let subscription = await registration.pushManager.getSubscription();
            
            if (!subscription) {
                this.log('Creating new push subscription...');
                
                // VAPID public key (replace with your actual key)
                const vapidPublicKey = document.body.getAttribute('data-vapid-key') ||
                                     'YOUR_VAPID_PUBLIC_KEY';
                
                if (vapidPublicKey === 'YOUR_VAPID_PUBLIC_KEY') {
                    this.log('Warning: VAPID key not configured');
                    return;
                }

                try {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey)
                    });
                    
                    this.log('New push subscription created:', subscription);
                    this.pushSubscription = subscription;
                    this.isPushEnabled = true;
                    
                    // Register with server
                    await this.registerPushSubscriptionWithServer(subscription);
                } catch (error) {
                    this.log('Error creating subscription:', error);
                }
            } else {
                this.log('Existing push subscription found');
                this.pushSubscription = subscription;
                this.isPushEnabled = true;
                
                // Register existing subscription with server
                await this.registerExistingSubscription(subscription);
            }
        } catch (error) {
            this.log('Error in automatic registration:', error);
        }
    }

    /**
     * Register existing subscription with server
     */
    async registerExistingSubscription(subscription) {
        try {
            const response = await fetch(this.basePath + '/ajax/register-push-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    subscription: subscription,
                    device_type: 'rider_app',
                    rider_id: this.riderId,
                    timestamp: new Date().toISOString()
                })
            });

            const result = await response.json();
            if (result.success) {
                this.log('Existing subscription registered with server');
                sessionStorage.setItem('push_notification_enabled', 'true');
            } else {
                this.log('Failed to register subscription:', result.message);
            }
        } catch (error) {
            this.log('Error registering existing subscription:', error);
        }
    }

    /**
     * Manual push notification toggle (for user control)
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
            this.showNotification('Error managing notifications', 'error');
        }
    }

    /**
     * Manual subscription method (when user explicitly enables)
     */
    async subscribeToPushNotifications() {
        try {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.showNotification('Push notifications not supported', 'error');
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const vapidPublicKey = document.body.getAttribute('data-vapid-key') ||
                                 'YOUR_VAPID_PUBLIC_KEY';

            if (vapidPublicKey === 'YOUR_VAPID_PUBLIC_KEY') {
                this.log('Warning: VAPID key not configured');
                return;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey)
            });

            this.log('Push subscription created:', subscription);
            this.pushSubscription = subscription;
            this.isPushEnabled = true;

            // Register with server
            await this.registerPushSubscriptionWithServer(subscription);
            this.showNotification('Push notifications enabled', 'success');
        } catch (error) {
            this.log('Error subscribing to push notifications:', error);
            this.showNotification('Failed to enable push notifications', 'error');
        }
    }

    /**
     * Register Push Subscription with Server
     */
    async registerPushSubscriptionWithServer(subscription) {
        try {
            const response = await fetch(this.basePath + '/ajax/register-push-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    subscription: subscription,
                    device_type: 'rider_app',
                    rider_id: this.riderId,
                    timestamp: new Date().toISOString()
                })
            });

            const result = await response.json();
            if (result.success) {
                this.log('Push subscription registered with server');
                sessionStorage.setItem('push_notification_enabled', 'true');
            } else {
                this.log('Failed to register subscription:', result.message);
            }
        } catch (error) {
            this.log('Error registering subscription with server:', error);
        }
    }

    /**
     * Unsubscribe from Push Notifications
     */
    async unsubscribeFromPushNotifications() {
        try {
            if (!this.pushSubscription) {
                this.showNotification('No active push subscription', 'info');
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await subscription.unsubscribe();
                this.log('Unsubscribed from push notifications');
                this.isPushEnabled = false;
                this.pushSubscription = null;
                sessionStorage.removeItem('push_notification_enabled');

                // Notify server
                await fetch(this.basePath + '/ajax/unregister-push-subscription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        rider_id: this.riderId,
                        timestamp: new Date().toISOString()
                    })
                });

                this.showNotification('Push notifications disabled', 'success');
            }
        } catch (error) {
            this.log('Error unsubscribing from push notifications:', error);
            this.showNotification('Error disabling push notifications', 'error');
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
     * Setup order notification listeners
     * Listen for new order assignments
     */
    setupOrderNotificationListeners() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                const data = event.data;
                
                if (data && data.type === 'NEW_ORDER_ASSIGNED') {
                    this.log('New order assigned:', data);
                    this.handleNewOrderNotification(data);
                } else if (data && data.type === 'ORDER_UPDATE') {
                    this.log('Order update received:', data);
                    this.handleOrderUpdate(data);
                }
            });
        }
    }

    /**
     * Handle new order assignment notification
     */
    handleNewOrderNotification(data) {
        const notification = {
            title: `New Order: #${data.order_number}`,
            message: `${data.customer_name} - ₹${data.amount}`,
            icon: '📦',
            priority: 'high'
        };
        
        this.showNotification(notification.message, 'info', 5000);
        
        // Emit custom event
        const event = new CustomEvent('orderAssigned', { detail: data });
        window.dispatchEvent(event);
    }

    /**
     * Handle order status updates
     */
    handleOrderUpdate(data) {
        const statusIcons = {
            'accepted': '✅',
            'picked_up': '📦',
            'on_the_way': '🚗',
            'arrived': '📍',
            'delivered': '✓',
            'failed': '❌'
        };

        const icon = statusIcons[data.status] || '📋';
        const notification = {
            title: `Order #${data.order_number}`,
            message: `Status: ${data.status}`,
            icon: icon,
            priority: 'medium'
        };

        this.showNotification(notification.message, 'info', 4000);
        
        // Emit custom event
        const event = new CustomEvent('orderStatusUpdated', { detail: data });
        window.dispatchEvent(event);
    }

    fixIncorrectURL() {
        // Only run this on online hosting
        if (this.isLocalhost) return;
        
        const currentUrl = window.location.href;
        
        // If we're on index listing page, redirect to correct URL
        if (currentUrl.includes('Index of') || currentUrl.endsWith('/delivery-panel/public/')) {
            window.location.href = '/delivery-panel/';
        }
    }

    async detectBasePath() {
        if (this.isLocalhost) {
            this.basePath = '/delivery-panel/public';
            this.log('Base path (localhost):', this.basePath);
        } else {
            this.basePath = '/delivery-panel/public';
            this.log('Base path (production):', this.basePath);
        }
    }

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(this.basePath + '/sw.js', {
                scope: '/delivery-panel/'
            })
            .then(registration => {
                this.log('Service Worker registered successfully');
                this.serviceWorkerRegistration = registration;
                
                // Check for updates periodically
                setInterval(() => {
                    registration.update();
                }, 60000);
            })
            .catch(error => {
                this.log('Service Worker registration failed:', error);
            });
        }
    }

    setupInstallPrompt() {
        const installButton = document.getElementById('installButton');
        this.log('Setting up install prompt, button found:', !!installButton);

        window.addEventListener('beforeinstallprompt', (e) => {
            this.log('beforeinstallprompt event fired');
            e.preventDefault();
            this.deferredPrompt = e;
            
            if (installButton) {
                installButton.style.display = 'block';
            }
        });

        if (installButton) {
            installButton.addEventListener('click', () => {
                this.showInstallPrompt();
            });
        }

        // Enhanced app installed handler
        window.addEventListener('appinstalled', (evt) => {
            this.log('App installed successfully');
            if (installButton) {
                installButton.style.display = 'none';
            }
            this.showNotification('Rider App installed! You can now use it offline.', 'success', 5000);
        });
    }

    showInstallPrompt() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            this.deferredPrompt.userChoice.then(choiceResult => {
                if (choiceResult.outcome === 'accepted') {
                    this.log('User accepted the install prompt');
                } else {
                    this.log('User dismissed the install prompt');
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
            message = 'Tap Share → Add to Home Screen to install the app';
        } else {
            message = 'Tap Menu (⋮) → Install App to install';
        }
        
        this.showNotification(message, 'info', 8000);
    }

    isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.navigator.standalone;
    }

    checkInstallability() {
        setTimeout(() => {
            if (this.isStandalone()) {
                this.log('App is running in standalone mode');
                document.body.classList.add('pwa-standalone');
                this.fixStandaloneURL();
            }
        }, 2000);
    }

    fixStandaloneURL() {
        const currentUrl = window.location.href;
        
        if (this.isLocalhost) {
            return;
        } else {
            if (currentUrl.includes('Index of') || currentUrl.endsWith('/delivery-panel/public/')) {
                window.location.href = '/delivery-panel/';
            }
        }
    }

    setupNetworkDetection() {
        window.addEventListener('online', () => {
            this.log('Connection restored');
            document.body.classList.remove('offline');
            this.showNotification('Back online', 'success', 3000);
        });

        window.addEventListener('offline', () => {
            this.log('Connection lost');
            document.body.classList.add('offline');
            this.showNotification('You are offline. Some features may be limited.', 'warning', 5000);
        });
    }

    detectStandaloneMode() {
        if (this.isStandalone()) {
            document.body.classList.add('pwa-standalone');
            this.log('Running in standalone mode');
        }
    }

    showNotification(message, type = 'info', duration = 3000) {
        const container = document.querySelector('.pwa-notification-container') || 
                         document.body;
        
        const notification = document.createElement('div');
        notification.className = `pwa-notification pwa-notification-${type}`;
        notification.innerHTML = `
            <div class="pwa-notification-content">
                <span class="pwa-notification-icon">${this.getIconForType(type)}</span>
                <span class="pwa-notification-message">${message}</span>
                <button class="pwa-notification-close">&times;</button>
            </div>
        `;
        
        container.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Close button handler
        notification.querySelector('.pwa-notification-close').addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });
        
        // Auto-close
        if (duration > 0) {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }
    }

    getIconForType(type) {
        const icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        return icons[type] || 'ℹ️';
    }

    /**
     * Convert base64 to Uint8Array for VAPID key
     */
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
    window.riderPWAHelper = new RiderPWAHelper();
});

// Add notification styles if not already present
if (!document.querySelector('#pwa-notification-styles')) {
    const style = document.createElement('style');
    style.id = 'pwa-notification-styles';
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
            gap: 10px;
        }
        
        .pwa-notification-message {
            flex: 1;
            font-size: 14px;
            color: #333;
        }
        
        .pwa-notification-icon {
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .pwa-notification-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #999;
            padding: 0;
            flex-shrink: 0;
        }
        
        .pwa-notification-close:hover {
            color: #333;
        }
        
        .pwa-notification-success {
            border-left-color: #28a745;
            background: linear-gradient(90deg, #d4edda 0%, #ffffff 100%);
        }
        
        .pwa-notification-error {
            border-left-color: #dc3545;
            background: linear-gradient(90deg, #f8d7da 0%, #ffffff 100%);
        }
        
        .pwa-notification-warning {
            border-left-color: #ffc107;
            background: linear-gradient(90deg, #fff3cd 0%, #ffffff 100%);
        }
        
        .pwa-notification-info {
            border-left-color: #17a2b8;
            background: linear-gradient(90deg, #d1ecf1 0%, #ffffff 100%);
        }
        
        .pwa-standalone .install-button {
            display: none !important;
        }
        
        body.offline {
            opacity: 0.8;
        }
        
        body.offline::before {
            content: "⚠️ OFFLINE MODE";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #ffc107;
            color: #000;
            text-align: center;
            padding: 5px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10001;
        }

        /* Push notification settings toggle */
        .notification-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #28a745;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
    `;
    document.head.appendChild(style);
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RiderPWAHelper;
}
