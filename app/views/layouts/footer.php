
<?php
// app/views/layouts/footer.php

// CRITICAL: Ensure UTF-8 encoding FIRST
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Set charset header if not already sent
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Enhanced security check that works with your bootstrap flow
if (!defined('ROOT_PATH') && !isset($pathConfig)) {
    // If this file is accessed directly, show error and exit
    if (strpos($_SERVER['SCRIPT_FILENAME'], 'footer.php') !== false) {
        exit('Direct access not permitted');
    }
    // If included but paths not set, try to initialize
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Try to include PathConfig if available
    $configPath = __DIR__ . '/../../../config/PathConfig.php';
    if (file_exists($configPath)) {
        require_once $configPath;
        $pathConfig = PathConfig::getInstance();
    } else {
        // Fallback values
        $base_url = '/phool-delivery-platform/public_html';
        $assets_path = $base_url . '/assets';
    }
}

// Use existing PathConfig or create instance
if (!isset($pathConfig) && class_exists('PathConfig')) {
    $pathConfig = PathConfig::getInstance();
}

// Set default values if PathConfig not available
$base_url = isset($pathConfig) ? $pathConfig->get('base_url') : ($base_url ?? '/phool-delivery-platform/public_html');
$assets_path = isset($pathConfig) ? $pathConfig->get('assets') : ($assets_path ?? $base_url . '/assets');

// Detect current page to conditionally show footer
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentUri = rtrim($currentUri, '/');
$isHomePage = ($currentUri === '/' || $currentUri === '' || $currentUri === $base_url);

// Initialize LanguageHelper if not already done
if (!function_exists('LanguageHelper') && file_exists(__DIR__ . '/../../../helpers/language.php')) {
    require_once __DIR__ . '/../../../helpers/language.php';
    if (method_exists('LanguageHelper', 'initialize')) {
        LanguageHelper::initialize();
    }
}
?>
        </main>

        <!-- Mobile Search Modal -->
        <div class="search-modal" id="searchModal" style="display: none;">
            <div class="search-modal-content">
                <div class="search-modal-header">
                    <h3><?= isset($languageHelper) ? $languageHelper->t('search_flowers', 'Search Flowers') : 'Search Flowers' ?></h3>
                    <button class="close-search-modal" id="closeSearchModal">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <form class="search-modal-form">
                    <input type="text" placeholder="<?= isset($languageHelper) ? $languageHelper->t('search_placeholder', 'Search for marigold, roses, jasmine...') : 'Search for marigold, roses, jasmine...' ?>" class="search-modal-input">
                    <button type="submit" class="search-modal-btn"><?= isset($languageHelper) ? $languageHelper->t('search', 'Search') : 'Search' ?></button>
                </form>
            </div>
        </div>

        <!-- Back to Top Button -->
        <button class="back-to-top" id="backToTop" data-aos="zoom-in" data-aos-delay="800">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="18 15 12 9 6 15"></polyline>
            </svg>
        </button>

        <!-- Footer - Conditionally displayed only on home page -->
        <?php if ($isHomePage): ?>
        <footer class="footer" style="background: linear-gradient(to right, #f8f9fa, #e9ecef); padding: 2rem 0 1rem; margin-top: 3rem; border-top: 1px solid #dee2e6; display: block;">
            <div class="footer-bottom" style="text-align: center;" data-aos="fade-up" data-aos-delay="100">
                <p style="margin: 0; color: #0f1011ff; font-size: 0.9rem; line-height: 1.6;">
                    &copy; <?php echo date('Y'); ?> Phool Delivery. <?= isset($languageHelper) ? $languageHelper->t('all_rights_reserved', 'All rights reserved.') : 'All rights reserved.' ?>
                    <span style="display: block; margin-top: 0.3rem; font-size: 0.8rem; color: #585f67ff;">
                        <?= isset($languageHelper) ? $languageHelper->t('powered_by', 'Powered by') : 'Powered by' ?>  
                    </span>
                </p>
            </div>
        </footer>

        <style>
        /* Hide footer on mobile for all pages */
        @media (max-width: 768px) {
            .footer {
                display: none !important;
            }
        }
        
        /* Hide footer on non-home pages for all devices */
        <?php if (!$isHomePage): ?>
            .footer {
                display: none !important;
            }
        <?php endif; ?>
        </style>
        <?php endif; ?>

    <!-- Fixed Buttons -->
  

<script>
// Enhanced logout confirmation with better redirection
function confirmLogout(event) {
    event.preventDefault();
    const logoutUrl = event.currentTarget.href;
    
    if (confirm('<?= LanguageHelper::t('confirm_logout', 'Are you sure you want to logout?') ?>')) {
        // Show loading state
        const logoutBtn = event.currentTarget;
        const originalText = logoutBtn.innerHTML;
        logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
        logoutBtn.style.opacity = '0.7';
        
        // Use fetch API for better error handling
        fetch(logoutUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            // Always redirect to home page after logout attempt
            window.location.href = '<?php echo $base_url; ?>/';
        })
        .catch(error => {
            // Silent fail - redirect to home page even if fetch fails
            window.location.href = '<?php echo $base_url; ?>/';
        });
        
        return false;
    }
    return false;
}

// Enhanced user dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const userDropdown = document.querySelector('.user-dropdown');
    const userAccountBtn = document.querySelector('.user-account-btn');
    const dropdownMenu = document.querySelector('#user-dropdown-menu');
    
    if (userAccountBtn && dropdownMenu && userDropdown) {
        // Toggle dropdown on button click
        userAccountBtn.addEventListener('click', function (e) {
            e.stopPropagation(); // Prevent event from bubbling to document
            const isHidden = dropdownMenu.style.display === 'none' || dropdownMenu.style.display === '';
            dropdownMenu.style.display = isHidden ? 'block' : 'none';
            userAccountBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!userDropdown.contains(e.target)) {
                dropdownMenu.style.display = 'none';
                userAccountBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
    
    // Handle location change
    document.querySelector('.change-location')?.addEventListener('click', function() {
        alert('Location selector would open here');
        // Implement location selection modal
    });
    
    // Handle search - only ONE search functionality
    document.querySelector('.search-btn')?.addEventListener('click', function() {
        const query = document.querySelector('.search-input').value;
        if(query.trim()) {
            window.location.href = '<?= $base_url ?>/search?q=' + encodeURIComponent(query);
        }
    });
    
    // Enter key in search
    document.querySelector('.search-input')?.addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            document.querySelector('.search-btn').click();
        }
    });
    
    // Cart and Message Count Management
    class CountManager {
        constructor() {
            this.cartCount = <?php echo $cart_count; ?>;
            this.messageCount = <?php echo $message_count; ?>;
            this.isUpdating = false;
            this.init();
        }

        init() {
            this.updateAllBadges();
            this.startPolling();
            this.setupEventListeners();
        }

        updateAllBadges() {
            this.updateCartBadges();
            this.updateMessageBadges();
        }

        updateCartBadges() {
            const cartBadges = [
                document.querySelector('.cart-badge:not(.message-badge)'),
                document.querySelector('.mobile-badge')
            ];
            
            cartBadges.forEach(badge => {
                if (badge) {
                    if (this.cartCount > 0) {
                        badge.textContent = this.cartCount;
                        badge.style.display = 'flex';
                        badge.classList.add('pulse-animation');
                        setTimeout(() => badge.classList.remove('pulse-animation'), 500);
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
        }

        updateMessageBadges() {
            const messageBadges = [
                document.querySelector('.message-badge'),
                document.querySelectorAll('.mobile-badge')[1]
            ];
            
            messageBadges.forEach(badge => {
                if (badge) {
                    if (this.messageCount > 0) {
                        badge.textContent = this.messageCount;
                        badge.style.display = 'flex';
                        badge.classList.add('pulse-animation');
                        setTimeout(() => badge.classList.remove('pulse-animation'), 500);
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
        }

        async fetchCounts() {
            if (this.isUpdating) return;
            
            this.isUpdating = true;
            
            try {
                const [cartResponse, messageResponse] = await Promise.allSettled([
                    fetch('<?php echo $base_url; ?>/cart/count'),
                    fetch('<?php echo $base_url; ?>/api/messages/unread-count')
                ]);

                if (cartResponse.status === 'fulfilled' && cartResponse.value.ok) {
                    const cartData = await cartResponse.value.json();
                    if (cartData.success && cartData.count !== this.cartCount) {
                        this.cartCount = cartData.count;
                        this.updateCartBadges();
                    }
                }

                if (messageResponse.status === 'fulfilled' && messageResponse.value.ok) {
                    const messageData = await messageResponse.value.json();
                    if (messageData.success && messageData.count !== this.messageCount) {
                        this.messageCount = messageData.count;
                        this.updateMessageBadges();
                    }
                }
            } catch (error) {
                console.log('Count update failed:', error);
            } finally {
                this.isUpdating = false;
            }
        }

        startPolling() {
            setTimeout(() => this.fetchCounts(), 2000);
            setInterval(() => this.fetchCounts(), 30000);
        }

        setupEventListeners() {
            document.addEventListener('cartUpdated', () => {
                setTimeout(() => this.fetchCounts(), 500);
            });

            document.addEventListener('messageRead', () => {
                setTimeout(() => this.fetchCounts(), 500);
            });

            window.addEventListener('storage', (event) => {
                if (event.key === 'cartUpdated' || event.key === 'messagesUpdated') {
                    this.fetchCounts();
                }
            });
        }

        refreshCartCount() {
            this.fetchCounts();
        }

        refreshMessageCount() {
            this.fetchCounts();
        }
    }

    // Initialize count manager
    window.countManager = new CountManager();

    // PWA Installation
    let deferredPrompt;
    let installButton = document.getElementById('installButton');

    function showPWAInstallModal() {
        const today = new Date().toDateString();
        const lastShownDate = '<?php echo $_SESSION['pwa_last_shown_date'] ?? ''; ?>';
        const pwaInstallShown = <?php echo $_SESSION['pwa_install_shown'] ? 'true' : 'false'; ?>;
        
        if (!pwaInstallShown || lastShownDate !== today) {
            const formData = new FormData();
            formData.append('pwa_install_shown', 'true');
            formData.append('pwa_last_shown_date', today);
            
            fetch('/phool-delivery-platform/public_html/update-pwa-session', {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).catch(error => {
                console.log('PWA session update not available');
            });
        }
    }

    function installPWA() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                }
                deferredPrompt = null;
            });
        }
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        setTimeout(() => {
            showPWAInstallModal();
        }, 5000);
    });
});

// Language switcher functionality
function changeLanguage(language) {
    const formData = new FormData();
    formData.append('language', language);
    
    fetch('/phool-delivery-platform/public_html/change-language', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload(true);
        }
    })
    .catch(error => {
        // Silent fail - language change error is not critical
    });
}

// Global functions for other components to trigger updates
function triggerCartUpdate() {
    if (window.countManager) {
        countManager.refreshCartCount();
    }
    localStorage.setItem('cartUpdated', Date.now().toString());
}

function triggerMessageUpdate() {
    if (window.countManager) {
        countManager.refreshMessageCount();
    }
    localStorage.setItem('messagesUpdated', Date.now().toString());
}

// Make functions globally available
window.triggerCartUpdate = triggerCartUpdate;
window.triggerMessageUpdate = triggerMessageUpdate;
</script>

    <!-- AOS Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <!-- JavaScript Files -->
    <script src="<?php echo $assets_path; ?>/js/app.js"></script>
    <script src="<?php echo $assets_path; ?>/js/components/navigation.js"></script>
    <script src="<?php echo $assets_path; ?>/js/components/cart.js"></script>
    <script src="<?php echo $assets_path; ?>/js/components/product.js"></script>
    <script src="<?php echo $assets_path; ?>/js/services/api.js"></script>
    <script src="<?php echo $assets_path; ?>/js/pwa.js"></script>
    
    <!-- External Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@sajanm/nepali-date-picker@4.0.1/dist/nepali.datepicker.v4.0.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/nepali-date-converter@3.4.0/dist/nepali-date-converter.umd.js"></script>

    <!-- Initialize AOS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS with custom settings
            AOS.init({
                duration: 800, // Animation duration in milliseconds
                easing: 'ease-in-out', // Easing function
                once: true, // Whether animation should happen only once
                mirror: false, // Whether elements should animate out while scrolling past them
                offset: 100 // Offset (in px) from the original trigger point
            });
            
            // Back to top button
            const backToTopBtn = document.getElementById('backToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopBtn.classList.add('visible');
                } else {
                    backToTopBtn.classList.remove('visible');
                }
            });
            
            backToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // Newsletter form submission
            const newsletterForm = document.querySelector('.newsletter-form');
            if (newsletterForm) {
                newsletterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const emailInput = this.querySelector('.newsletter-input');
                    const email = emailInput.value.trim();
                    
                    if (email && isValidEmail(email)) {
                        alert('<?= isset($languageHelper) ? $languageHelper->t('newsletter_thank_you', 'Thank you for subscribing to our newsletter!') : 'Thank you for subscribing to our newsletter!' ?>');
                        emailInput.value = '';
                    } else {
                        alert('<?= isset($languageHelper) ? $languageHelper::t('enter_valid_email', 'Please enter a valid email address.') : 'Please enter a valid email address.' ?>');
                    }
                });
            }
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
        });
    </script>

<?php $gaId = getenv('GA_MEASUREMENT_ID') ?: ''; if ($gaId !== ''): ?>
<!-- Google Analytics (rendered only when GA_MEASUREMENT_ID is configured) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId, ENT_QUOTES) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= htmlspecialchars($gaId, ENT_QUOTES) ?>');
</script>
<?php endif; ?>
</body>
</html>