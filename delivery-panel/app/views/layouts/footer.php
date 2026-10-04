                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5" role="contentinfo" itemscope itemtype="https://schema.org/Organization">
        <!-- Hidden Schema.org structured data -->
        <meta itemprop="name" content="Phool Delivery Nepal">
        <meta itemprop="email" content="support@phooldelivery.example">
        <meta itemprop="telephone" content="+977-9803962360">
        <meta itemprop="address" content="Kathmandu, Nepal">
        
        <!-- Organization Schema for SEO -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "Phool Delivery Nepal",
            "url": "<?php echo htmlspecialchars(rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/delivery-panel', '/')); ?>",
            "email": "support@phooldelivery.example",
            "telephone": "+977-9803962360",
            "foundingDate": "2023",
            "founder": {
                "@type": "Person",
                "name": "Phool Delivery Team"
            },
            "areaServed": {
                "@type": "City",
                "name": ["Kathmandu", "Bhaktapur", "Banepa"],
                "containedInPlace": {
                    "@type": "Country",
                    "name": "Nepal"
                }
            }
        }
        </script>
    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5" role="contentinfo" itemscope itemtype="https://schema.org/Organization">
        <!-- Hidden Schema.org structured data -->
        <meta itemprop="name" content="Phool Delivery Nepal">
        <meta itemprop="email" content="support@phooldelivery.example">
        <meta itemprop="telephone" content="+977-9803962360">
        <meta itemprop="address" content="Kathmandu, Nepal">
        
        <!-- Organization Schema for SEO -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "Phool Delivery Nepal",
            "url": "<?php echo htmlspecialchars(rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/delivery-panel', '/')); ?>",
            "email": "support@phooldelivery.example",
            "telephone": "+977-9803962360",
            "foundingDate": "2023",
            "founder": {
                "@type": "Person",
                "name": "Phool Delivery Team"
            },
            "areaServed": {
                "@type": "City",
                "name": ["Kathmandu", "Bhaktapur", "Banepa"],
                "containedInPlace": {
                    "@type": "Country",
                    "name": "Nepal"
                }
            }
        }
        </script>
        <style>
            footer {
                background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%) !important;
                border-top: 2px solid #FF6B35;
            }

            .footer-content {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 30px;
                margin-bottom: 30px;
            }

            .footer-section {
                display: flex;
                flex-direction: column;
            }

            .footer-section h5 {
                font-size: 1.1rem;
                font-weight: 700;
                margin-bottom: 15px;
                color: #FF6B35;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .footer-section p {
                font-size: 0.9rem;
                line-height: 1.6;
                color: #ccc;
                margin-bottom: 10px;
            }

            .footer-section ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .footer-section ul li {
                margin-bottom: 8px;
            }

            .footer-section ul li a {
                color: #ccc;
                text-decoration: none;
                font-size: 0.9rem;
                transition: all 0.3s ease;
            }

            .footer-section ul li a:hover {
                color: #FF6B35;
                padding-left: 5px;
            }

            .social-links {
                display: flex;
                gap: 12px;
                margin-top: 10px;
            }

            .social-links a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                background-color: #FF6B35;
                color: white;
                border-radius: 50%;
                text-decoration: none;
                transition: all 0.3s ease;
                font-size: 1rem;
                title: social link
            }

            .social-links a:hover {
                background-color: #e55a2a;
                transform: translateY(-3px);
            }

            .footer-bottom {
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                padding-top: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 15px;
            }

            .footer-bottom p {
                margin: 0;
                font-size: 0.9rem;
            }

            .footer-links-bottom {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }

            .footer-links-bottom a {
                color: #ccc;
                text-decoration: none;
                font-size: 0.9rem;
                transition: color 0.3s ease;
            }

            .footer-links-bottom a:hover {
                color: #FF6B35;
            }

            /* Responsive Tablet Design */
            @media (max-width: 992px) {
                .footer-content {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 25px;
                }

                .footer-section h5 {
                    font-size: 1rem;
                }

                .footer-section p,
                .footer-section ul li a {
                    font-size: 0.85rem;
                }
            }

            /* Responsive Mobile Design */
            @media (max-width: 576px) {
                footer {
                    padding-top: 3rem !important;
                    padding-bottom: 4rem !important;
                }

                .footer-content {
                    grid-template-columns: 1fr;
                    gap: 20px;
                    margin-bottom: 20px;
                }

                .footer-section {
                    border: 1px solid rgba(255, 107, 53, 0.2);
                    padding: 15px;
                    border-radius: 8px;
                    background-color: rgba(255, 107, 53, 0.05);
                }

                .footer-section h5 {
                    font-size: 0.95rem;
                    margin-bottom: 12px;
                }

                .footer-section p,
                .footer-section ul li a {
                    font-size: 0.8rem;
                }

                .footer-section ul li {
                    margin-bottom: 6px;
                }

                .social-links {
                    gap: 10px;
                }

                .social-links a {
                    width: 36px;
                    height: 36px;
                    font-size: 0.9rem;
                }

                .footer-bottom {
                    flex-direction: column;
                    text-align: center;
                    border-top: 1px solid rgba(255, 255, 255, 0.1);
                    padding-top: 15px;
                }

                .footer-bottom p {
                    font-size: 0.8rem;
                }

                .footer-links-bottom {
                    width: 100%;
                    justify-content: center;
                    gap: 12px;
                    font-size: 0.8rem;
                }

                .footer-links-bottom a {
                    font-size: 0.8rem;
                }
            }

            /* Adjust body padding when mobile nav is visible */
            @media (max-width: 768px) {
                body {
                    padding-bottom: 70px;
                }
            }
        </style>

        <div class="container-fluid px-3 px-md-4">
            <div class="footer-content">
                <!-- About Section -->
                <div class="footer-section">
                    <h5><i class="fas fa-leaf"></i>About Phool Delivery</h5>
                    <p>Professional delivery management platform connecting riders with customers across Nepal. We provide reliable, fast, and efficient flower delivery services with advanced tracking technology.</p>
                    <div class="social-links">
                        <a href="#" title="Facebook" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Twitter" rel="noopener noreferrer"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="Instagram" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="LinkedIn" rel="noopener noreferrer"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h5><i class="fas fa-link"></i>Quick Links</h5>
                    <ul>
                        <li><a href="<?php echo htmlspecialchars(app_url('/dashboard')); ?>">Dashboard</a></li>
                        <li><a href="<?php echo htmlspecialchars(app_url('/orders/assigned')); ?>">My Orders</a></li>
                        <li><a href="<?php echo htmlspecialchars(app_url('/earnings')); ?>">Earnings</a></li>
                        <li><a href="<?php echo htmlspecialchars(app_url('/profile')); ?>">My Profile</a></li>
                        <li><a href="<?php echo htmlspecialchars(app_url('/statistics')); ?>">Statistics</a></li>
                    </ul>
                </div>

                <!-- Support & Resources -->
                <div class="footer-section">
                    <h5><i class="fas fa-headset"></i>Support</h5>
                    <ul>
                        <li><a href="<?php echo htmlspecialchars(app_url('/support')); ?>">Help & Support</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Training</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="footer-section">
                    <h5><i class="fas fa-info-circle"></i>Contact Info</h5>
                    <p>
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:support@phooldelivery.example" style="color: #ccc; text-decoration: none;">support@phooldelivery.example</a>
                    </p>
                    <p>
                        <i class="fas fa-phone"></i>
                        <a href="tel:+9779803962360" style="color: #ccc; text-decoration: none;">+977 9803962360</a>
                    </p>
                    <p>
                        <i class="fas fa-map-marker-alt"></i>
                        <span itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                            <span itemprop="addressLocality">Kathmandu</span>, <span itemprop="addressCountry">Nepal</span>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Footer Bottom - Legal & Copyright -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <span itemprop="name">Phool Delivery Nepal</span>. All rights reserved.</p>
                <div class="footer-links-bottom">
                    <a href="#" rel="nofollow">Privacy Policy</a>
                    <a href="#" rel="nofollow">Terms of Service</a>
                    <a href="#" rel="nofollow">Cookie Policy</a>
                    <a href="#" rel="nofollow">Disclaimer</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Mobile Bottom Navigation (Mobile only) -->
    <nav class="mobile-bottom-nav show" id="mobileBottomNav">
        <a href="<?php echo htmlspecialchars(app_url('/dashboard')); ?>" class="mobile-bottom-nav-item" id="navHome">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="<?php echo htmlspecialchars(app_url('/orders/assigned')); ?>" class="mobile-bottom-nav-item" id="navOrders">
            <i class="fas fa-box"></i>
            <span>Orders</span>
            <?php 
            $pendingOrders = $GLOBALS['pendingOrdersCount'] ?? 0;
            if ($pendingOrders > 0): ?>
            <span class="mobile-nav-badge"><?php echo $pendingOrders; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo htmlspecialchars(app_url('/support')); ?>" class="mobile-bottom-nav-item" id="navMessages">
            <i class="fas fa-comments"></i>
            <span>Messages</span>
            <?php 
            $openTickets = $GLOBALS['openTicketsCount'] ?? 0;
            if ($openTickets > 0): ?>
            <span class="mobile-nav-badge"><?php echo $openTickets; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo htmlspecialchars(app_url('/profile')); ?>" class="mobile-bottom-nav-item" id="navAccount">
            <i class="fas fa-user"></i>
            <span>Account</span>
        </a>
    </nav>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 CSS and JS for searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Custom JS -->
    <?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
    <script>
        window.APP_URL = '<?php echo addslashes(rtrim(app_url(''), '/')); ?>';
        window.APP_PUBLIC = '<?php echo addslashes(rtrim(app_url(''), '/')); ?>/assets';
        
        // Toast Notification System
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            
            // Icons for different types
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            
            toast.innerHTML = `<i class="${icons[type]}"></i><span>${message}</span>`;
            container.appendChild(toast);
            
            // Auto remove after duration
            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        // Initialize Select2 for searchable dropdowns
        $(document).ready(function() {
            // Apply Select2 to all select elements in modals
            $('select[name="bank_name"]').select2({
                dropdownParent: $('#editBankingModal'),
                allowClear: true,
                placeholder: 'Select a bank'
            });
            
            $('select[name="state"]').select2({
                dropdownParent: $('#editAddressModal'),
                allowClear: true,
                placeholder: 'Select province'
            });
            
            $('select[name="district"]').select2({
                dropdownParent: $('#editAddressModal'),
                allowClear: true,
                placeholder: 'Select district'
            });
            
            $('select[name="city"]').select2({
                dropdownParent: $('#editAddressModal'),
                allowClear: true,
                placeholder: 'Select city'
            });
        });
        
        // Mobile bottom nav active state
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navItems = document.querySelectorAll('.mobile-bottom-nav-item');
            
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (currentPath.includes('/dashboard') && href.includes('/dashboard')) {
                    item.classList.add('active');
                } else if (currentPath.includes('/orders') && href.includes('/orders') && !currentPath.includes('/profile')) {
                    item.classList.add('active');
                } else if (currentPath.includes('/support') && href.includes('/support')) {
                    item.classList.add('active');
                } else if (currentPath.includes('/profile') && href.includes('/profile')) {
                    item.classList.add('active');
                }
            });
            
            // Show/hide mobile nav based on screen size
            function toggleMobileNav() {
                const mobileNav = document.getElementById('mobileBottomNav');
                if (window.innerWidth <= 768) {
                    mobileNav.classList.add('show');
                } else {
                    mobileNav.classList.remove('show');
                }
            }
            
            toggleMobileNav();
            window.addEventListener('resize', toggleMobileNav);
        });
    </script>
    <script src="<?php echo htmlspecialchars(app_url('/assets/js/app.js')); ?>"></script>
</body>
</html>
