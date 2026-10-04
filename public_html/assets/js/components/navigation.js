// public/assets/js/components/navigation.js

// Production environment detection - use global if available, otherwise detect
var isProduction = window.isProduction !== undefined ? window.isProduction : (window.location.hostname.includes('phooldelivery.com') || window.location.protocol === 'https:');
// DEBUG LOGS COMPLETELY DISABLED - no console output in any environment
var debugLog = function() {};
// Expose globally to prevent redeclaration errors in other scripts
window.isProduction = isProduction;
window.debugLog = function() {};

function initNavigation() {
    debugLog('Navigation initialization started');
    
    // Mobile search functionality - toggle search bar
    const mobileSearchBtn = document.querySelector('.mobile-search-btn');
    const mobileSearchBar = document.querySelector('.mobile-search-bar');
    const mobileSearchClose = document.querySelector('.mobile-search-close');
    
    if (mobileSearchBtn && mobileSearchBar) {
        mobileSearchBtn.addEventListener('click', () => {
            mobileSearchBar.classList.toggle('active');
            // Focus on search input when it opens
            if (mobileSearchBar.classList.contains('active')) {
                const searchInput = mobileSearchBar.querySelector('.mobile-search-input');
                setTimeout(() => searchInput.focus(), 100);
            }
        });
    }
    
    if (mobileSearchClose && mobileSearchBar) {
        mobileSearchClose.addEventListener('click', () => {
            mobileSearchBar.classList.remove('active');
        });
    }
    
    // Desktop search functionality
    const desktopSearchForm = document.querySelector('.search-form');
    if (desktopSearchForm) {
        desktopSearchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const searchInput = desktopSearchForm.querySelector('.search-input');
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm) {
                // Search functionality - dynamically detect base URL
                var basePath = window.location.pathname.includes('/phool-delivery/') ? '/phool-delivery/public_html' : '';
                window.location.href = basePath + '/search?q=' + encodeURIComponent(searchTerm);
                searchInput.value = '';
            }
        });
    }
    
    // Mobile search functionality
    const mobileSearchForm = document.querySelector('.mobile-search-form');
    if (mobileSearchForm) {
        mobileSearchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const searchInput = mobileSearchForm.querySelector('.mobile-search-input');
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm) {
                // Search functionality - dynamically detect base URL
                var basePath = window.location.pathname.includes('/phool-delivery/') ? '/phool-delivery/public_html' : '';
                window.location.href = basePath + '/search?q=' + encodeURIComponent(searchTerm);
                searchInput.value = '';
                mobileSearchBar.classList.remove('active');
            }
        });
    }
    
    // Active state for mobile nav items
    const navItems = document.querySelectorAll('.nav-item');
    const desktopNavItems = document.querySelectorAll('.desktop-nav a');
    const currentPath = window.location.pathname;
    
    // Set active state based on current path
    function setActiveNavItems() {
        // Mobile nav items
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (currentPath === href || currentPath === href + '/') {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
        
        // Desktop nav items
        desktopNavItems.forEach(item => {
            const href = item.getAttribute('href');
            if (currentPath === href || currentPath === href + '/') {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }
    
    setActiveNavItems();
    
    // Header scroll effect
    const desktopHeader = document.querySelector('.desktop-header');
    if (desktopHeader) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                desktopHeader.classList.add('scrolled');
            } else {
                desktopHeader.classList.remove('scrolled');
            }
        });
    }
    
    debugLog('Navigation initialized successfully');
}

// Make function available globally
window.initNavigation = initNavigation;

// Initialize navigation when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavigation);
} else {
    initNavigation();
}