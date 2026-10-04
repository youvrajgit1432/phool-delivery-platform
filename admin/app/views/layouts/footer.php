<?php
// app/views/layouts/footer.php
?>
            </main>
        </div>
    </div>
 

    <!-- Footer -->
    <footer class="py-3 bg-light mt-auto">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between small">
                <div class="text-muted">
                    &copy; <?php echo date("Y"); ?> Phool Delivery. All Rights Reserved.
                </div>
                <div class="text-muted">
                    Powered by Devitar Krishi Firm.
                </div>
                <div>
                    <a href="#">Privacy Policy</a>
                    &middot;
                    <a href="#">Terms &amp; Conditions</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom JavaScript -->
    <script src="<?php echo $admin_assets_path; ?>/js/admin.js"></script>

    <script>
// ========== SIDEBAR ACCORDION & ACTIVE STATE MANAGEMENT ==========
        
        // Get all collapse elements in sidebar
        const collapseElements = document.querySelectorAll('.sidebar .collapse');
        const dropdownToggleButtons = document.querySelectorAll('.sidebar .nav-link[data-bs-toggle="collapse"]');
        
        // Add Bootstrap accordion event listeners
        collapseElements.forEach(collapseElement => {
            collapseElement.addEventListener('show.bs.collapse', function(e) {
                // Close all other collapse elements
                collapseElements.forEach(otherCollapse => {
                    if (otherCollapse !== collapseElement && otherCollapse.classList.contains('show')) {
                        const bsCollapse = new bootstrap.Collapse(otherCollapse, { toggle: false });
                        bsCollapse.hide();
                    }
                });
            });
        });
        
        // Add custom toggle functionality to dropdown buttons
        dropdownToggleButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const collapseId = this.getAttribute('href');
                const collapseElement = document.querySelector(collapseId);
                
                if (collapseElement) {
                    // Check if already open
                    const isOpen = collapseElement.classList.contains('show');
                    
                    if (isOpen) {
                        // Close it
                        const bsCollapse = new bootstrap.Collapse(collapseElement, { toggle: false });
                        bsCollapse.hide();
                    } else {
                        // Open it
                        const bsCollapse = new bootstrap.Collapse(collapseElement, { toggle: false });
                        bsCollapse.show();
                    }
                }
            });
        });
        
        // Auto-expand dropdown if it contains an active link
        function autoExpandActiveDropdown() {
            const activeLink = document.querySelector('.sidebar .nav-link.active:not([data-bs-toggle])');
            if (activeLink && activeLink.parentElement) {
                // Check if active link is inside a collapse
                const collapseParent = activeLink.closest('.collapse');
                if (collapseParent) {
                    const bsCollapse = new bootstrap.Collapse(collapseParent, { toggle: false });
                    bsCollapse.show();
                    
                    // Also highlight the parent dropdown button
                    const dropdownBtn = collapseParent.previousElementSibling;
                    if (dropdownBtn && dropdownBtn.classList.contains('nav-link')) {
                        dropdownBtn.classList.remove('collapsed');
                        dropdownBtn.setAttribute('aria-expanded', 'true');
                    }
                }
            }
        }
        
        // Highlight current page in sidebar - IMPROVED FOR EXACT MATCHING
        function highlightCurrentPage() {
            const currentPath = window.location.pathname;
            const currentFilename = currentPath.split('/').pop() || 'index.php';
            const currentSearch = window.location.search;
            
            // Remove active class from all links first
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Get all nav links
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (!href) return;
                
                const isDropdownToggle = link.getAttribute('data-bs-toggle') === 'collapse';
                
                // Skip dropdown toggle buttons
                if (isDropdownToggle) return;
                
                // Parse link URL and current URL
                const linkParts = href.split('?');
                const linkFilename = linkParts[0].split('/').pop();
                const linkSearch = linkParts[1] ? '?' + linkParts[1] : '';
                
                // Exact match logic
                let isMatch = false;
                
                // Check filename match
                if (linkFilename === currentFilename) {
                    // If link has query params, they must match exactly
                    if (linkSearch) {
                        isMatch = linkSearch === currentSearch;
                    } else {
                        // If link has no query params, only match if URL also has no query params
                        isMatch = !currentSearch;
                    }
                }
                
                if (isMatch) {
                    link.classList.add('active');
                }
            });
            
            // Re-expand active dropdown
            autoExpandActiveDropdown();
        }
        
        // Call on page load
        highlightCurrentPage();
        // Call on page load
        highlightCurrentPage();
        
        // Re-highlight on navigation
        window.addEventListener('popstate', highlightCurrentPage);
        
        // ========== SIDEBAR RESPONSIVENESS & STATE MANAGEMENT ==========
    
    // Navigation Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('main');
        const topbar = document.querySelector('.topbar');
        const footer = document.querySelector('footer');
        const body = document.body;
        
        // Create sidebar toggle button for desktop
        const sidebarToggle = document.createElement('button');
        sidebarToggle.className = 'btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0 d-none d-lg-inline';
        sidebarToggle.id = 'sidebarToggle';
        sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
        sidebarToggle.type = 'button';
        
        // Insert sidebar toggle button in desktop view
        const navbarBrand = document.querySelector('.navbar-brand');
        if (navbarBrand && navbarBrand.parentNode) {
            navbarBrand.parentNode.insertBefore(sidebarToggle, navbarBrand.nextSibling);
        }
        
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileSearchForm = document.getElementById('mobileSearchForm');
        const searchToggle = document.getElementById('searchToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        // Initialize sidebar state from localStorage
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        const isMobileView = window.matchMedia('(max-width: 768px)').matches;
        
        if (sidebarCollapsed && !isMobileView && sidebar) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('sidebar-collapsed');
            if (footer) footer.classList.add('sidebar-collapsed');
        }
        
        // Function to update all responsive elements
        function updateSidebarState(isCollapsed) {
            if (sidebar) {
                isCollapsed ? sidebar.classList.add('collapsed') : sidebar.classList.remove('collapsed');
            }
            if (mainContent) {
                isCollapsed ? mainContent.classList.add('sidebar-collapsed') : mainContent.classList.remove('sidebar-collapsed');
            }
            if (footer) {
                isCollapsed ? footer.classList.add('sidebar-collapsed') : footer.classList.remove('sidebar-collapsed');
            }
        }
        
        // Desktop sidebar toggle
        const toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isNowCollapsed = !sidebar.classList.contains('collapsed');
                updateSidebarState(isNowCollapsed);
                localStorage.setItem('sidebarCollapsed', isNowCollapsed);
            });
        }
        
        // Mobile menu toggle
        if (mobileMenuToggle && sidebar) {
            mobileMenuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const isMobileMode = window.matchMedia('(max-width: 768px)').matches;
                if (isMobileMode) {
                    sidebar.classList.toggle('mobile-open');
                    sidebar.classList.toggle('show');
                    if (sidebarOverlay) sidebarOverlay.classList.toggle('show');
                    body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
                }
            });
        }
        
        // Mobile search toggle
        if (searchToggle && mobileSearchForm) {
            searchToggle.addEventListener('click', function(e) {
                e.preventDefault();
                mobileSearchForm.classList.toggle('show');
            });
        }
        
        // Close sidebar when clicking overlay
        if (sidebarOverlay && sidebar) {
            sidebarOverlay.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.remove('mobile-open');
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                body.style.overflow = '';
            });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isMobileMode = window.matchMedia('(max-width: 768px)').matches;
            if (isMobileMode && sidebar && sidebar.classList.contains('mobile-open')) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggle = mobileMenuToggle && mobileMenuToggle.contains(event.target);
                const isClickOnOverlay = sidebarOverlay && sidebarOverlay.contains(event.target);
                
                if (!isClickInsideSidebar && !isClickOnToggle && !isClickOnOverlay) {
                    sidebar.classList.remove('mobile-open');
                    sidebar.classList.remove('show');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                    body.style.overflow = '';
                }
            }
            if (mobileSearchForm && mobileSearchForm.classList.contains('show')) {
                if (!mobileSearchForm.contains(event.target) && !searchToggle.contains(event.target)) {
                    mobileSearchForm.classList.remove('show');
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            if (isMobile && sidebar) {
                sidebar.classList.remove('mobile-open');
                sidebar.classList.remove('show');
                if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                body.style.overflow = '';
            }
            if (!isMobile && sidebar) {
                sidebar.classList.remove('mobile-open');
                sidebar.classList.remove('show');
                if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                const sidebarState = localStorage.getItem('sidebarCollapsed') === 'true';
                updateSidebarState(sidebarState);
            }
        });
        
        // Disable DataTables alert popups
        if (typeof $.fn.dataTable !== 'undefined' && $.fn.dataTable.ext) {
            $.fn.dataTable.ext.errMode = 'none';
        }

        // Initialize DataTables
        try {
            $('table').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        } catch (dtInitErr) {
            console.warn('DataTables initialization warning:', dtInitErr);
        }
        
        // Enable tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Enable popovers
        $('[data-bs-toggle="popover"]').popover();
    });
    </script>
</body>
</html>