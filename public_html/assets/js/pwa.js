// assets/js/pwa.js - Enhanced with Automatic Push Registration & Auto-enable - COMPLETE VERSION

class PWAHelper {
    constructor() {
        this.deferredPrompt = null;
        this.isLocalhost = window.location.hostname === 'localhost' || 
                          window.location.hostname === '127.0.0.1' ||
                          window.location.hostname === '::1';
        // Only enable debug logging on localhost — NOW DISABLED (no console output)
        this.debug = false;
        this.pushSubscription = null;
        this.isPushEnabled = false;
        this.serviceWorkerRegistration = null;
        this.autoRegistrationAttempted = false;
        this.notificationsAutoEnabled = false;
        this.init();
    }

    log(message, data = null) {
        if (this.debug) {
            console.log(`[PWA] ${message}`, data || '');
        }
    }

    async init() {
        this.log('Initializing PWA features with automatic notification enablement...');
        this.log('Current URL:', window.location.href);
        this.log('Is Localhost:', this.isLocalhost);
        
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
    }

    /**
     * AUTO-ENABLE NOTIFICATIONS without user interaction
     */
    async autoEnableNotifications() {
        try {
            this.log('Auto-enabling notifications...');
            
            // Check if notifications are already enabled
            const response = await fetch(this.basePath + '/api/notifications/current');
            const data = await response.json();
            
            if (data.success && !data.has_preference) {
                // Notifications not set yet, auto-enable them
                this.log('Auto-enabling notification preferences...');
                
                const autoEnableResponse = await fetch(this.basePath + '/api/notifications/auto-enable', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        auto_enabled: true,
                        timestamp: new Date().toISOString()
                    })
                });
                
                const result = await autoEnableResponse.json();
                
                if (result.success) {
                    this.log('Notifications auto-enabled successfully');
                    this.notificationsAutoEnabled = true;
                    
                    // Store in session storage
                    sessionStorage.setItem('notifications_auto_enabled', 'true');
                    sessionStorage.setItem('notifications_enabled', 'true');
                    
                    this.showNotification('Notifications enabled for better experience!', 'success', 3000);
                }
            } else if (data.has_preference) {
                this.log('Notifications already configured');
                this.notificationsAutoEnabled = true;
            }
            
        } catch (error) {
            this.log('Auto-enable notifications failed:', error);
            // Don't show error to user for automatic process
        }
    }

    setupServiceWorkerMessaging() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.log('Message from service worker:', event.data);
                
                switch (event.data.type) {
                    case 'PUSH_AUTO_REGISTRATION_SUCCESS':
                        this.log('Automatic push registration successful');
                        this.isPushEnabled = true;
                        this.notificationsAutoEnabled = true;
                        break;
                    case 'PUSH_AUTO_REGISTRATION_FAILED':
                        this.log('Automatic push registration failed:', event.data.error);
                        // Don't show error to user for automatic process
                        break;
                    case 'PUSH_REGISTRATION_SUCCESS':
                        this.isPushEnabled = true;
                        this.showNotification('Push notifications enabled!', 'success', 3000);
                        break;
                    case 'PUSH_REGISTRATION_FAILED':
                        this.isPushEnabled = false;
                        this.showNotification('Failed to enable push notifications: ' + event.data.error, 'error', 5000);
                        break;
                }
            });
        }
    }

    /**
     * Automatic Push Notification Initialization
     * Registers for push notifications automatically on first visit
     */
    async initializeAutomaticPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            this.log('Push notifications not supported');
            return;
        }

        try {
            // Check if we've already attempted auto-registration
            if (this.autoRegistrationAttempted) {
                this.log('Auto-registration already attempted, skipping');
                return;
            }

            this.autoRegistrationAttempted = true;

            // Check notification permission
            const permission = await Notification.permission;
            this.log('Current notification permission:', permission);

            if (permission === 'default') {
                // Permission not decided yet - wait a bit and try automatic registration
                setTimeout(() => this.attemptAutomaticRegistration(), 2000);
            } else if (permission === 'granted') {
                // Permission already granted - proceed with automatic registration
                await this.attemptAutomaticRegistration();
            } else {
                this.log('Notification permission denied, skipping auto-registration');
            }
        } catch (error) {
            this.log('Error in automatic push initialization:', error);
        }
    }

    /**
     * Attempt automatic push registration
     */
    async attemptAutomaticRegistration() {
        try {
            this.log('Attempting automatic push registration...');

            // Wait for service worker to be ready
            if (!this.serviceWorkerRegistration) {
                this.serviceWorkerRegistration = await navigator.serviceWorker.ready;
            }

            // Check if already subscribed
            const existingSubscription = await this.serviceWorkerRegistration.pushManager.getSubscription();
            if (existingSubscription) {
                this.log('Already subscribed to push notifications');
                this.isPushEnabled = true;
                this.notificationsAutoEnabled = true;
                
                // Register existing subscription with server
                await this.registerExistingSubscription(existingSubscription);
                return;
            }

            // Request permission silently (without showing prompt)
            // This will work if the user has previously granted permission
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                this.log('Permission granted, proceeding with automatic registration');
                
                // Trigger registration via service worker
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'AUTO_REGISTER_PUSH',
                        browserName: this.getBrowserName(),
                        browserVersion: this.getBrowserVersion(),
                        platform: navigator.platform
                    });
                }
            } else {
                this.log('Automatic registration skipped - permission not granted');
            }
        } catch (error) {
            this.log('Automatic registration attempt failed:', error);
        }
    }

    /**
     * Register existing subscription with server
     */
    async registerExistingSubscription(subscription) {
        try {
            const subscriptionJSON = subscription.toJSON();
            
            const deviceData = {
                device_token: subscriptionJSON.endpoint,
                subscription_data: subscriptionJSON,
                device_type: 'web',
                browser_name: this.getBrowserName(),
                browser_version: this.getBrowserVersion(),
                platform: navigator.platform,
                user_agent: navigator.userAgent,
                ip_address: await this.getClientIP(),
                auto_registered: true
            };

            const response = await fetch(this.basePath + '/api/notifications/register-device', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(deviceData)
            });

            const data = await response.json();
            
            if (data.success) {
                this.log('Existing push subscription registered with server');
                this.isPushEnabled = true;
                this.notificationsAutoEnabled = true;
            } else {
                throw new Error(data.message || 'Registration failed');
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
            return this.isPushEnabled;
        } catch (error) {
            this.log('Error toggling push notifications:', error);
            this.showNotification('Failed to update notification settings', 'error', 3000);
            return this.isPushEnabled;
        }
    }

    /**
     * Manual subscription method (when user explicitly enables)
     */
    async subscribeToPushNotifications() {
        try {
            // Get VAPID public key from server
            const response = await fetch(this.basePath + '/api/notifications/vapid-key');
            const data = await response.json();
            
            if (!data.success) {
                throw new Error('Failed to get VAPID key: ' + (data.message || 'Unknown error'));
            }

            const vapidPublicKey = data.publicKey;
            
            if (!vapidPublicKey) {
                throw new Error('VAPID public key is empty');
            }

            // Convert base64 URL safe to Uint8Array
            const applicationServerKey = this.urlBase64ToUint8Array(vapidPublicKey);

            // Get service worker registration
            const registration = await navigator.serviceWorker.ready;

            // Check existing subscription
            this.pushSubscription = await registration.pushManager.getSubscription();
            
            if (this.pushSubscription) {
                this.log('Existing push subscription found:', this.pushSubscription);
                // Update server with existing subscription
                await this.registerPushSubscriptionWithServer();
                this.isPushEnabled = true;
                return;
            }

            // Subscribe to push with detailed options
            this.pushSubscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });

            this.log('New push subscription created:', this.pushSubscription);

            // Register with server
            await this.registerPushSubscriptionWithServer();
            this.isPushEnabled = true;

        } catch (error) {
            this.log('Error subscribing to push notifications:', error);
            this.isPushEnabled = false;
            
            // Show user-friendly error message
            if (error.name === 'NotAllowedError') {
                this.showNotification('Please allow notifications in your browser settings to receive push notifications.', 'warning', 5000);
            } else {
                this.showNotification('Failed to enable push notifications. Please try again.', 'error', 5000);
            }
            throw error;
        }
    }

    /**
     * Enhanced Device Registration with Server
     */
    async registerPushSubscriptionWithServer() {
        if (!this.pushSubscription) {
            this.log('No push subscription available');
            return;
        }

        try {
            const subscriptionJSON = this.pushSubscription.toJSON();

            // Prepare device data
            const deviceData = {
                device_token: subscriptionJSON.endpoint,
                subscription_data: subscriptionJSON,
                device_type: 'web',
                browser_name: this.getBrowserName(),
                browser_version: this.getBrowserVersion(),
                platform: navigator.platform,
                user_agent: navigator.userAgent,
                ip_address: await this.getClientIP(),
                manually_registered: true
            };

            this.log('Registering device with data:', deviceData);

            const response = await fetch(this.basePath + '/api/notifications/register-device', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(deviceData)
            });

            const data = await response.json();
            
            if (data.success) {
                this.log('Push subscription registered successfully with server');
                this.showNotification('Push notifications enabled successfully!', 'success', 3000);
                
                // Store in session
                sessionStorage.setItem('push_notification_enabled', 'true');
                
                // Notify service worker of successful registration
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'PUSH_REGISTRATION_SUCCESS',
                        data: data
                    });
                }
            } else {
                throw new Error(data.message || 'Server returned error');
            }
        } catch (error) {
            this.log('Error registering push subscription:', error);
            throw error;
        }
    }

    /**
     * Enhanced Unsubscribe with Server Sync
     */
    async unsubscribeFromPushNotifications() {
        try {
            if (this.pushSubscription) {
                const deviceToken = this.pushSubscription.endpoint;
                
                // Unsubscribe from push service
                const success = await this.pushSubscription.unsubscribe();
                if (success) {
                    this.pushSubscription = null;
                    this.isPushEnabled = false;
                    this.notificationsAutoEnabled = false;
                    
                    // Remove from session
                    sessionStorage.removeItem('push_notification_enabled');
                    
                    // Notify server about unsubscription
                    await fetch(this.basePath + '/api/notifications/unregister-device', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            device_token: deviceToken
                        })
                    });
                    
                    this.log('Unsubscribed from push notifications');
                    this.showNotification('Push notifications disabled', 'info', 3000);
                }
            }
        } catch (error) {
            this.log('Error unsubscribing from push notifications:', error);
            throw error;
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
                permission: 'not-supported',
                autoEnabled: this.notificationsAutoEnabled
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
     * Check if notifications are auto-enabled
     */
    isNotificationsAutoEnabled() {
        return this.notificationsAutoEnabled || 
               sessionStorage.getItem('notifications_auto_enabled') === 'true' ||
               sessionStorage.getItem('notifications_enabled') === 'true';
    }

    // FIX: Only fix URLs when online, NEVER on localhost
    fixIncorrectURL() {
        // Only run this on online hosting, NEVER on localhost
        if (this.isLocalhost) {
            this.log('Skipping URL fix on localhost');
            return;
        }
        
        const currentUrl = window.location.href;
        
        // If we're on the wrong URL (public_html), redirect to correct one
        if (currentUrl.includes('phooldelivery.example') && currentUrl.includes('/phool-delivery/public_html')) {
            const correctUrl = 'https://www.phooldelivery.example/';
            this.log('Fixing incorrect URL, redirecting to:', correctUrl);
            window.location.replace(correctUrl);
            return;
        }
        
        // If we're on index listing page, redirect to main site
        if (currentUrl.includes('Index of') || currentUrl.endsWith('/phool-delivery/public_html/')) {
            const correctUrl = 'https://www.phooldelivery.example/';
            this.log('Redirecting from directory listing to:', correctUrl);
            window.location.replace(correctUrl);
            return;
        }
    }

    async detectBasePath() {
        // Detect the correct base path from current location
        const pathname = window.location.pathname;
        
        // Remove trailing slashes and last segment if it's a file
        let basePath = pathname.split('/').filter(Boolean);
        
        // Check if we're in a subdirectory (like /phool-delivery/public_html or /phool-delivery)
        if (basePath[0] === 'phool-delivery') {
            // We're on localhost in a subdirectory
            this.basePath = '/phool-delivery/public_html';
            this.swPath = '/phool-delivery/public_html/sw.js';
            this.manifestPath = '/phool-delivery/public_html/manifest.webmanifest';
            this.startUrl = '/phool-delivery/public_html/';
        } else {
            // Production server or root domain
            this.basePath = '';
            this.swPath = '/sw.js';
            this.manifestPath = '/manifest.webmanifest';
            this.startUrl = '/';
        }
        
        this.log('Detected base path:', {
            basePath: this.basePath,
            swPath: this.swPath,
            manifestPath: this.manifestPath,
            startUrl: this.startUrl,
            currentPathname: pathname
        });
    }

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            this.log('Service Worker supported');
            
            window.addEventListener('load', () => {
                const swUrl = this.swPath;
                this.log('Registering Service Worker:', swUrl);
                
                navigator.serviceWorker.register(swUrl)
                    .then((registration) => {
                        this.log('SW registered successfully:', registration);
                        this.serviceWorkerRegistration = registration;
                    })
                    .catch((registrationError) => {
                        this.log('SW registration failed:', registrationError);
                    });
            });
        }
    }

    setupInstallPrompt() {
        const installButton = document.getElementById('installButton');
        this.log('Setting up install prompt, button found:', !!installButton);

        window.addEventListener('beforeinstallprompt', (e) => {
            this.log('🎉 BEFORE INSTALL PROMPT FIRED!');
            e.preventDefault();
            this.deferredPrompt = e;

            if (installButton) {
                installButton.style.display = 'flex';
                installButton.classList.add('pulsing');
                
                setTimeout(() => {
                    if (this.deferredPrompt && installButton.style.display === 'flex') {
                        this.showInstallGuidance();
                    }
                }, 3000);
            }
        });

        if (installButton) {
            installButton.addEventListener('click', () => {
                this.showInstallPrompt();
            });
        }

        // FIX: Enhanced app installed handler with environment-aware redirect
        window.addEventListener('appinstalled', (evt) => {
            this.log('🎊 PWA was installed successfully!');
            
            if (installButton) {
                installButton.style.display = 'none';
            }
            this.deferredPrompt = null;
            
            // Force redirect to correct URL after installation (only if online)
            setTimeout(() => {
                this.forceCorrectLandingPage();
            }, 1000);
        });
    }

    // FIX: Environment-aware redirect after installation
    forceCorrectLandingPage() {
        const currentUrl = window.location.href;
        
        if (this.isLocalhost) {
            // On localhost, stay on localhost
            const localCorrectUrl = 'https://localhost/phool-delivery/public_html/';
            if (currentUrl !== localCorrectUrl && !currentUrl.includes('localhost/phool-delivery/public_html')) {
                this.log('FORCE REDIRECT after install to LOCALHOST:', localCorrectUrl);
                window.location.href = localCorrectUrl;
            }
        } else {
            // On online hosting, redirect to online URL
            const onlineCorrectUrl = 'https://www.phooldelivery.example/';
            if (currentUrl !== onlineCorrectUrl && !currentUrl.endsWith('phooldelivery.example/')) {
                this.log('FORCE REDIRECT after install to ONLINE:', onlineCorrectUrl);
                window.location.href = onlineCorrectUrl;
            }
        }
    }

    showInstallPrompt() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            
            this.deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    this.log('✅ User accepted install');
                    this.showNotification('Installing app...', 'success', 3000);
                } else {
                    this.log('❌ User declined install');
                    this.showNotification('Installation cancelled', 'warning', 3000);
                }
                this.deferredPrompt = null;
            });
        } else {
            this.showManualInstallInstructions();
        }
    }

    showInstallGuidance() {
        this.showNotification('📱 Install our app for better experience!', 'info', 5000);
    }

    showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        let message = '';
        
        if (isIOS) {
            message = 'To install: Tap share button 📤 → "Add to Home Screen"';
        } else {
            message = 'To install: Tap menu ⋮ → "Install App" or "Add to Home Screen"';
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
                this.log('Running as installed PWA');
                document.body.classList.add('pwa-standalone');
                
                // FIX: Ensure correct URL in standalone mode
                this.fixStandaloneURL();
            }
        }, 2000);
    }

    // FIX: Environment-aware URL fix for standalone mode
    fixStandaloneURL() {
        const currentUrl = window.location.href;
        
        if (this.isLocalhost) {
            // Fix localhost URLs in standalone mode
            const localCorrectUrl = 'https://localhost/phool-delivery/public_html/';
            if (currentUrl.includes('/phool-delivery/public_html') || 
                currentUrl.endsWith('/phool-delivery/public_html')) {
                this.log('Fixing standalone mode URL on LOCALHOST from:', currentUrl);
                window.history.replaceState({}, document.title, localCorrectUrl);
                
                // If replaceState doesn't work, do full redirect
                setTimeout(() => {
                    if (window.location.href !== localCorrectUrl) {
                        window.location.href = localCorrectUrl;
                    }
                }, 500);
            }
        } else {
            // Fix online URLs in standalone mode
            const onlineCorrectUrl = 'https://www.phooldelivery.example/';
            if (currentUrl.includes('/phool-delivery/public_html') || 
                currentUrl.endsWith('/phool-delivery/public_html')) {
                this.log('Fixing standalone mode URL on ONLINE from:', currentUrl);
                window.history.replaceState({}, document.title, onlineCorrectUrl);
                
                // If replaceState doesn't work, do full redirect
                setTimeout(() => {
                    if (window.location.href !== onlineCorrectUrl) {
                        window.location.href = onlineCorrectUrl;
                    }
                }, 500);
            }
        }
    }

    setupNetworkDetection() {
        window.addEventListener('online', () => {
            document.body.classList.remove('offline');
            this.showNotification('Connection restored', 'success', 2000);
        });

        window.addEventListener('offline', () => {
            document.body.classList.add('offline');
            this.showNotification('You are offline', 'warning', 3000);
        });

        if (!navigator.onLine) {
            document.body.classList.add('offline');
        }
    }

    detectStandaloneMode() {
        if (this.isStandalone()) {
            document.body.classList.add('pwa-standalone');
            this.log('Running in standalone PWA mode');
            this.fixStandaloneURL();
        }
    }

    showNotification(message, type = 'info', duration = 3000) {
        const existingNotifications = document.querySelectorAll('.pwa-notification');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `pwa-notification pwa-notification-${type}`;
        notification.innerHTML = `
            <div class="pwa-notification-content">
                <span class="pwa-notification-message">${message}</span>
                <button class="pwa-notification-close">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 100);

        const removeNotification = () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        };

        const autoRemove = setTimeout(removeNotification, duration);
        notification.querySelector('.pwa-notification-close').addEventListener('click', () => {
            clearTimeout(autoRemove);
            removeNotification();
        });
    }

    /**
     * Get browser name
     */
    getBrowserName() {
        const userAgent = navigator.userAgent;
        if (userAgent.includes('Chrome')) return 'Chrome';
        if (userAgent.includes('Firefox')) return 'Firefox';
        if (userAgent.includes('Safari')) return 'Safari';
        if (userAgent.includes('Edge')) return 'Edge';
        return 'Unknown';
    }

    /**
     * Get browser version
     */
    getBrowserVersion() {
        const userAgent = navigator.userAgent;
        const match = userAgent.match(/(chrome|firefox|safari|edge|version)\/([0-9]+)/i);
        return match ? match[2] : 'Unknown';
    }

    /**
     * Get client IP address
     */
    async getClientIP() {
        try {
            const response = await fetch('https://api.ipify.org?format=json');
            const data = await response.json();
            return data.ip;
        } catch (error) {
            this.log('Error getting client IP:', error);
            return 'unknown';
        }
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
    window.pwaHelper = new PWAHelper();
});

// Auto-enable notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if we need to auto-enable notifications
    setTimeout(() => {
        if (window.pwaHelper && !window.pwaHelper.isNotificationsAutoEnabled()) {
            window.pwaHelper.autoEnableNotifications();
        }
    }, 1000);
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
        
        .offline::before {
            content: "⚠️ Offline";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #ffc107;
            color: #000;
            text-align: center;
            padding: 5px;
            font-size: 12px;
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
    module.exports = PWAHelper;
}