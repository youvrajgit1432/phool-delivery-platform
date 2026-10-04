<?php
// app/views/account/notifications.php

$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('notification_settings', 'Notification Settings') . " - Phool Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

$success_message = $success_message ?? '';
$error_message = $error_message ?? '';
$preferences = $preferences ?? [
    'push_notifications_enabled' => true,
    'main_notification_enabled' => true,
    'offers_notifications' => true,
    'delivery_tracking_notifications' => true,
    'system_notifications' => true
];

$device_registration_forced = $device_registration_forced ?? false;
$devices = $devices ?? [];
?>

<div class="account-container">
    <div class="account-header" data-aos="fade-up" data-aos-delay="100">
        <h2><?= LanguageHelper::t('notification_settings', 'Notification Settings') ?></h2>
        <p><?= LanguageHelper::t('manage_notification_preferences', 'Manage how and when you receive notifications') ?></p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" data-aos="zoom-in" data-aos-delay="150">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error" data-aos="zoom-in" data-aos-delay="150">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($device_registration_forced): ?>
        <div class="alert alert-info" data-aos="zoom-in" data-aos-delay="150">
            <i class="fas fa-info-circle"></i>
            <?= LanguageHelper::t('device_registered_successfully', 'Your device has been successfully registered for push notifications') ?>
        </div>
    <?php endif; ?>

    <!-- Registered Devices Section -->
    <?php if (!empty($devices)): ?>
    <div class="notification-section" data-aos="fade-up" data-aos-delay="200">
        <div class="section-header">
            <h3><i class="fas fa-mobile-alt"></i> <?= LanguageHelper::t('registered_devices', 'Registered Devices') ?></h3>
            <p><?= LanguageHelper::t('manage_registered_devices', 'Devices registered for push notifications') ?></p>
        </div>
        
        <div class="devices-list">
            <?php foreach ($devices as $device): ?>
            <div class="device-item">
                <div class="device-info">
                    <div class="device-icon">
                        <i class="fas fa-<?= $device['device_type'] === 'android' ? 'android' : ($device['device_type'] === 'ios' ? 'apple' : 'desktop') ?>"></i>
                    </div>
                    <div class="device-details">
                        <h4><?= htmlspecialchars($device['browser_name'] ?? 'Unknown Browser') ?></h4>
                        <p><?= htmlspecialchars($device['platform'] ?? 'Unknown Platform') ?></p>
                        <small><?= LanguageHelper::t('registered_on', 'Registered on') ?>: <?= date('M j, Y g:i A', strtotime($device['created_at'])) ?></small>
                    </div>
                </div>
                <div class="device-status">
                    <span class="status-badge <?= $device['is_active'] ? 'active' : 'inactive' ?>">
                        <?= $device['is_active'] ? LanguageHelper::t('active', 'Active') : LanguageHelper::t('inactive', 'Inactive') ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="notification-settings-content">
        <form id="notificationSettingsForm" class="notification-form" data-aos="zoom-in" data-aos-delay="200">
            <!-- Main Notification Toggle -->
            <div class="notification-section" data-aos="fade-up" data-aos-delay="250">
                <div class="section-header">
                    <h3><i class="fas fa-bell"></i> <?= LanguageHelper::t('notification_preferences', 'Notification Preferences') ?></h3>
                    <p><?= LanguageHelper::t('control_notification_types', 'Control what types of notifications you receive') ?></p>
                </div>
                
                <div class="notification-option main-toggle">
                    <div class="option-content">
                        <div class="option-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="option-details">
                            <h4><?= LanguageHelper::t('enable_all_notifications', 'Enable All Notifications') ?></h4>
                            <p><?= LanguageHelper::t('turn_on_off_all', 'Turn all notifications on or off') ?></p>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="mainNotificationToggle" <?= $preferences['main_notification_enabled'] ? 'checked' : '' ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>

            <!-- Individual Notification Options -->
            <div class="notification-section" data-aos="fade-up" data-aos-delay="300">
                <div class="section-header">
                    <h3><i class="fas fa-sliders-h"></i> <?= LanguageHelper::t('notification_types', 'Notification Types') ?></h3>
                    <p><?= LanguageHelper::t('customize_specific_notifications', 'Customize specific notification types') ?></p>
                </div>
                
                <div class="individual-options" id="individualOptions">
                    <!-- Special Offers -->
                    <div class="notification-option">
                        <div class="option-content">
                            <div class="option-icon" style="background: #fff3e0;">
                                <i class="fas fa-gift" style="color: #ff9800;"></i>
                            </div>
                            <div class="option-details">
                                <h4><?= LanguageHelper::t('special_offers', 'Special Offers & Promotions') ?></h4>
                                <p><?= LanguageHelper::t('discounts_promotions', 'Discounts, promotions, and special offers') ?></p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="offersNotifications" <?= $preferences['offers_notifications'] ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <!-- Delivery Updates -->
                    <div class="notification-option">
                        <div class="option-content">
                            <div class="option-icon" style="background: #e8f5e8;">
                                <i class="fas fa-shipping-fast" style="color: #4caf50;"></i>
                            </div>
                            <div class="option-details">
                                <h4><?= LanguageHelper::t('delivery_updates', 'Delivery & Order Updates') ?></h4>
                                <p><?= LanguageHelper::t('order_tracking_updates', 'Order tracking and delivery status updates') ?></p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="deliveryNotifications" <?= $preferences['delivery_tracking_notifications'] ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <!-- System Notifications -->
                    <div class="notification-option" hidden>
                        <div class="option-content">
                            <div class="option-icon" style="background: #e3f2fd;">
                                <i class="fas fa-info-circle" style="color: #2196f3;"></i>
                            </div>
                            <div class="option-details">
                                <h4><?= LanguageHelper::t('system_notifications', 'System & Account Notifications') ?></h4>
                                <p><?= LanguageHelper::t('important_updates', 'Important account updates and announcements') ?></p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="systemNotifications" <?= $preferences['system_notifications'] ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <!-- Push Notifications -->
                    <div class="notification-option" hidden>
                        <div class="option-content">
                            <div class="option-icon" style="background: #f3e5f5;">
                                <i class="fas fa-mobile-alt" style="color: #9c27b0;"></i>
                            </div>
                            <div class="option-details">
                                <h4><?= LanguageHelper::t('push_notifications', 'Push Notifications') ?></h4>
                                <p><?= LanguageHelper::t('realtime_notifications', 'Real-time notifications on your device') ?></p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="pushNotifications" <?= $preferences['push_notifications_enabled'] ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Device Registration Section -->
            <div class="notification-section" data-aos="fade-up" data-aos-delay="350" hidden>
                <div class="section-header">
                    <h3><i class="fas fa-sync-alt"></i> <?= LanguageHelper::t('device_registration', 'Device Registration') ?></h3>
                    <p><?= LanguageHelper::t('manage_device_registration', 'Force register your device for push notifications') ?></p>
                </div>
                
                <div class="device-registration-actions">
                    <button type="button" id="forceRegisterBtn" class="btn btn-warning">
                        <i class="fas fa-bolt"></i> <?= LanguageHelper::t('force_register_device', 'Force Register This Device') ?>
                    </button>
                    <p class="help-text"><?= LanguageHelper::t('force_register_help', 'Use this if your device is not receiving push notifications') ?></p>
                </div>
            </div>

            <!-- Back Button Only -->
            <div class="form-actions" data-aos="fade-up" data-aos-delay="400">
                <a href="<?= $pathConfig->url('account') ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> <?= LanguageHelper::t('back_to_dashboard', 'Back') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('notificationSettingsForm');
    const mainToggle = document.getElementById('mainNotificationToggle');
    const forceRegisterBtn = document.getElementById('forceRegisterBtn');
    
    // Individual toggles
    const offersToggle = document.getElementById('offersNotifications');
    const deliveryToggle = document.getElementById('deliveryNotifications');
    const systemToggle = document.getElementById('systemNotifications');
    const pushToggle = document.getElementById('pushNotifications');
    
    // Auto-save timer
    let autoSaveTimer = null;
    const AUTO_SAVE_DELAY = 1000; // 1 second delay after last change
    
    // Store original state for comparison
    let originalState = {
        main: mainToggle.checked,
        offers: offersToggle.checked,
        delivery: deliveryToggle.checked,
        system: systemToggle.checked,
        push: pushToggle.checked
    };
    
    // Main toggle controls all individual toggles
    mainToggle.addEventListener('change', function() {
        const isEnabled = this.checked;
        
        // Animate the change
        animateToggleChange(isEnabled);
        
        // Update all toggles
        offersToggle.checked = isEnabled;
        deliveryToggle.checked = isEnabled;
        systemToggle.checked = isEnabled;
        pushToggle.checked = isEnabled;
        
        // Update individual toggles disabled state
        updateIndividualTogglesState(isEnabled);
        
        // Auto-save after changes
        scheduleAutoSave();
    });
    
    // Individual toggles update main toggle and trigger auto-save
    [offersToggle, deliveryToggle, systemToggle, pushToggle].forEach(toggle => {
        toggle.addEventListener('change', function() {
            updateMainToggle();
            scheduleAutoSave();
        });
    });
    
    // Force register device button
    forceRegisterBtn.addEventListener('click', function() {
        forceRegisterDevice();
    });
    
    function updateMainToggle() {
        const allChecked = offersToggle.checked && deliveryToggle.checked && 
                          systemToggle.checked && pushToggle.checked;
        const anyUnchecked = !offersToggle.checked || !deliveryToggle.checked || 
                           !systemToggle.checked || !pushToggle.checked;
        
        if (allChecked) {
            mainToggle.checked = true;
            mainToggle.indeterminate = false;
        } else if (anyUnchecked) {
            mainToggle.indeterminate = true;
        } else {
            mainToggle.checked = false;
            mainToggle.indeterminate = false;
        }
    }
    
    function updateIndividualTogglesState(disabled) {
        [offersToggle, deliveryToggle, systemToggle, pushToggle].forEach(toggle => {
            toggle.disabled = disabled;
        });
    }
    
    function animateToggleChange(isEnabled) {
        const options = document.querySelectorAll('.notification-option:not(.main-toggle)');
        
        options.forEach((option, index) => {
            setTimeout(() => {
                option.style.transform = isEnabled ? 'translateY(-2px) scale(1.02)' : 'translateY(0) scale(1)';
                setTimeout(() => {
                    option.style.transform = '';
                }, 300);
            }, index * 100);
        });
    }
    
    function scheduleAutoSave() {
        // Clear existing timer
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }
        
        // Set new timer
        autoSaveTimer = setTimeout(() => {
            savePreferences();
        }, AUTO_SAVE_DELAY);
    }
    
    function savePreferences() {
        const preferences = {
            push_notifications_enabled: pushToggle.checked,
            main_notification_enabled: mainToggle.checked,
            offers_notifications: offersToggle.checked,
            delivery_tracking_notifications: deliveryToggle.checked,
            system_notifications: systemToggle.checked
        };
        
        // Add loading state to form
        form.classList.add('loading');
        
        fetch('<?= $pathConfig->url('api/notifications/update-settings') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(preferences)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast('<?= LanguageHelper::t('notification_preferences_updated', 'Notification preferences updated successfully') ?>', 'success');
                
                // Update original state
                originalState = {
                    main: mainToggle.checked,
                    offers: offersToggle.checked,
                    delivery: deliveryToggle.checked,
                    system: systemToggle.checked,
                    push: pushToggle.checked
                };
                
                // If device was registered during save, show additional message
                if (data.device_registered) {
                    showToast('<?= LanguageHelper::t('device_registered_successfully', 'Device registered successfully for push notifications') ?>', 'success');
                }
            } else {
                showToast(data.message || '<?= LanguageHelper::t('error_updating_preferences', 'Error updating preferences') ?>', 'error');
                
                // Revert to original state on error
                revertToOriginalState();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('<?= LanguageHelper::t('error_updating_preferences', 'Error updating preferences') ?>', 'error');
            
            // Revert to original state on error
            revertToOriginalState();
        })
        .finally(() => {
            form.classList.remove('loading');
        });
    }
    
    function forceRegisterDevice() {
        forceRegisterBtn.disabled = true;
        forceRegisterBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= LanguageHelper::t('registering', 'Registering...') ?>';
        
        // Try to get device token from service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(function(registration) {
                return registration.pushManager.getSubscription();
            }).then(function(subscription) {
                if (subscription) {
                    return subscription.endpoint;
                }
                return null;
            }).then(function(deviceToken) {
                // Call force register API
                return fetch('<?= $pathConfig->url('api/notifications/force-register-device') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        device_token: deviceToken,
                        force_registration: true
                    })
                });
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('<?= LanguageHelper::t('device_force_registered', 'Device forcefully registered successfully') ?>', 'success');
                } else {
                    showToast(data.message || '<?= LanguageHelper::t('error_registering_device', 'Error registering device') ?>', 'error');
                }
            }).catch(error => {
                console.error('Error:', error);
                showToast('<?= LanguageHelper::t('error_registering_device', 'Error registering device') ?>', 'error');
            }).finally(() => {
                forceRegisterBtn.disabled = false;
                forceRegisterBtn.innerHTML = '<i class="fas fa-bolt"></i> <?= LanguageHelper::t('force_register_device', 'Force Register This Device') ?>';
            });
        } else {
            showToast('<?= LanguageHelper::t('push_not_supported', 'Push notifications are not supported in this browser') ?>', 'error');
            forceRegisterBtn.disabled = false;
            forceRegisterBtn.innerHTML = '<i class="fas fa-bolt"></i> <?= LanguageHelper::t('force_register_device', 'Force Register This Device') ?>';
        }
    }
    
    function revertToOriginalState() {
        mainToggle.checked = originalState.main;
        offersToggle.checked = originalState.offers;
        deliveryToggle.checked = originalState.delivery;
        systemToggle.checked = originalState.system;
        pushToggle.checked = originalState.push;
        
        updateMainToggle();
    }
    
    function showToast(message, type) {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.toast');
        existingToasts.forEach(toast => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        });
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.animation = 'slideInRight 0.3s ease';
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
    
    // Add loading state styles
    const style = document.createElement('style');
    style.textContent = `
        .notification-form.loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .notification-form.loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            z-index: 10;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .toast-success {
            background: #4caf50;
        }
        
        .toast-error {
            background: #f44336;
        }
        
        .toast-warning {
            background: #ff9800;
        }
        
        .toast-info {
            background: #2196f3;
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .devices-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .device-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .device-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .device-icon {
            width: 40px;
            height: 40px;
            background: #e3f2fd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #2196f3;
        }
        
        .device-details h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .device-details p {
            margin: 0 0 5px 0;
            color: #666;
        }
        
        .device-details small {
            color: #999;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-badge.active {
            background: #e8f5e8;
            color: #4caf50;
        }
        
        .status-badge.inactive {
            background: #ffebee;
            color: #f44336;
        }
        
        .device-registration-actions {
            text-align: center;
            padding: 20px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #fafafa;
        }
        
        .device-registration-actions .help-text {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    // Initialize main toggle state
    updateMainToggle();
});
</script>
<style>
.notification-settings-content {
    max-width: 800px;
    margin: 0 auto;
}

.notification-section {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.notification-section:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.section-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e9ecef;
}

.section-header h3 {
    margin: 0 0 8px 0;
    color: #2d5016;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-header p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.notification-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    margin-bottom: 12px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    position: relative;
}

.notification-option:hover {
    border-color: #2d5016;
    background: #f8f9fa;
    transform: translateY(-2px);
}

.notification-option.main-toggle {
    background: #f8f9fa;
    border-color: #2d5016;
}

.option-content {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}

.option-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: transform 0.3s ease;
}

.notification-option:hover .option-icon {
    transform: scale(1.1);
}

.option-details h4 {
    margin: 0 0 4px 0;
    color: #2d5016;
    font-size: 1rem;
}

.option-details p {
    margin: 0;
    color: #6c757d;
    font-size: 0.875rem;
}

/* Switch styles */
.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #2d5016;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

input:disabled + .slider {
    background-color: #e9ecef;
    cursor: not-allowed;
}

input:disabled + .slider:before {
    background-color: #f8f9fa;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
    align-items: center;
    padding-top: 24px;
    border-top: 1px solid #e9ecef;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-primary {
    background-color: #2d5016;
    color: white;
}

.btn-primary:hover {
    background-color: #1f3710;
    transform: translateY(-2px);
}

.btn-outline {
    background-color: transparent;
    color: #2d5016;
    border: 1px solid #2d5016;
}

.btn-outline:hover {
    background-color: #2d5016;
    color: white;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Toast styles */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 400px;
    color: white;
}

.toast-success {
    background-color: #4caf50;
}

.toast-error {
    background-color: #f44336;
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Animations */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@media (max-width: 768px) {
    .notification-section {
        padding: 16px;
    }
    
    .notification-option {
        padding: 12px;
    }
    
    .option-content {
        gap: 12px;
    }
    
    .option-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .switch {
        width: 50px;
        height: 28px;
    }
    
    .slider:before {
        height: 20px;
        width: 20px;
    }
    
    input:checked + .slider:before {
        transform: translateX(22px);
    }
}
</style>