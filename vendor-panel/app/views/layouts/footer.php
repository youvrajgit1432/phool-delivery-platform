<?php
/**
 * Footer Layout - Vendor Panel
 * Global footer for all pages
 */

// Get order and product counts for mobile nav badges
$pendingCount = 0;
$assignedCount = 0;
$acceptedCount = 0;
$preparingCount = 0;
$readyCount = 0;
$totalNonCompleteCount = 0;
$productsCount = 0;

if (isset($_SESSION['vendor_id'])) {
    try {
        // Use the global database connection
        $dbConfig = $GLOBALS['config']['database'] ?? [];
        if (!empty($dbConfig)) {
            $db = \App\Database\Connection::getInstance($dbConfig);
            $vendorId = $_SESSION['vendor_id'];
            
            // Get count for each order status (non-complete)
            $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'assigned']);
            $row = $stmt->fetch();
            $assignedCount = $row['cnt'] ?? 0;
            
            $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'accepted']);
            $row = $stmt->fetch();
            $acceptedCount = $row['cnt'] ?? 0;
            
            $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'preparing']);
            $row = $stmt->fetch();
            $preparingCount = $row['cnt'] ?? 0;
            
            $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'ready']);
            $row = $stmt->fetch();
            $readyCount = $row['cnt'] ?? 0;
            
            // Total non-complete orders
            $totalNonCompleteCount = $assignedCount + $acceptedCount + $preparingCount + $readyCount;
            
            // Get linked products count
            $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_product_map WHERE vendor_id = ? AND unlinked_at IS NULL', [$vendorId]);
            $row = $stmt->fetch();
            $productsCount = $row['cnt'] ?? 0;
        }
    } catch (Exception $e) {
        error_log('[footer.php] Database error: ' . $e->getMessage());
    }
}
?>

    </div> <!-- /.container -->
</main>

<!-- Footer -->
<footer class="app-footer" style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #ccc; padding: 3rem 2rem 1rem; margin-top: 3rem; border-top: 2px solid #667eea; font-size: 0.9rem;" role="contentinfo" itemscope itemtype="https://schema.org/Organization">
    <!-- Hidden Schema.org structured data -->
    <meta itemprop="name" content="Phool Delivery Nepal">
    <meta itemprop="email" content="vendor@phooldelivery.example">
    <meta itemprop="telephone" content="+977-9800000000">
    <meta itemprop="address" content="Kathmandu, Nepal">
    
    <!-- Organization Schema for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Phool Delivery Nepal",
        "url": "<?php echo htmlspecialchars(rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/vendor-panel', '/')); ?>",
        "email": "vendor@phooldelivery.example",
        "telephone": "+977-9800000000",
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
        @media (max-width: 768px) {
            .app-footer {
                display: none !important;
            }
            
            body {
                padding-bottom: 60px !important;
            }
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
        
        .footer-section h6 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-section ul li {
            margin-bottom: 0.75rem;
        }
        
        .footer-section ul li a {
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-section ul li a:hover {
            color: #667eea;
            padding-left: 5px;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            font-size: 1.25rem;
        }
        
        .social-links a {
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            color: #667eea;
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.5rem;
            text-align: center;
            color: #888;
        }
        
        .footer-bottom p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        .footer-links-bottom {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .footer-links-bottom a {
            color: #ccc;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .footer-links-bottom a:hover {
            color: #667eea;
        }
        
        @media (max-width: 992px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 25px;
            }
            
            .footer-section h6 {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .footer-section {
                border: 1px solid rgba(102, 126, 234, 0.2);
                padding: 15px;
                border-radius: 8px;
                background-color: rgba(102, 126, 234, 0.05);
            }
            
            .footer-section h6 {
                font-size: 0.95rem;
            }
        }
    </style>
    
    <div class="container-fluid">
        <div class="footer-content">
            <!-- About Section -->
            <div class="footer-section">
                <h6><i class="fas fa-leaf"></i>About Phool Delivery</h6>
                <p style="margin-bottom: 1rem; color: #ccc;">Professional flower delivery and vendor management platform connecting sellers with customers across Nepal. Grow your business with our comprehensive vendor tools.</p>
                <div class="social-links">
                    <a href="#" title="Facebook" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter" rel="noopener noreferrer"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="Instagram" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="LinkedIn" rel="noopener noreferrer"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-section">
                <h6><i class="fas fa-link"></i>Quick Links</h6>
                <ul>
                    <li><a href="<?php echo htmlspecialchars(vendor_url('/dashboard')); ?>">Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>">Orders</a></li>
                    <li><a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>">Products</a></li>
                    <li><a href="<?php echo htmlspecialchars(vendor_url('/payouts')); ?>">Payouts</a></li>
                    <li><a href="<?php echo htmlspecialchars(vendor_url('/account')); ?>">Account</a></li>
                </ul>
            </div>

            <!-- Support & Resources -->
            <div class="footer-section">
                <h6><i class="fas fa-headset"></i>Vendor Support</h6>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Documentation</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Training</a></li>
                    <li><a href="#">Vendor Guide</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="footer-section">
                <h6><i class="fas fa-info-circle"></i>Contact Info</h6>
                <p>
                    <i class="fas fa-envelope" style="margin-right: 8px;"></i>
                    <a href="mailto:vendor@phooldelivery.example" style="color: #ccc; text-decoration: none;">vendor@phooldelivery.example</a>
                </p>
                <p>
                    <i class="fas fa-phone" style="margin-right: 8px;"></i>
                    <a href="tel:+9779800000000" style="color: #ccc; text-decoration: none;">+977 9800000000</a>
                </p>
                <p>
                    <i class="fas fa-map-marker-alt" style="margin-right: 8px;"></i>
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
                <a href="#" rel="nofollow">Vendor Agreement</a>
            </div>
        </div>
    </div>
</footer>

<!-- Mobile Bottom Navigation (Mobile only) -->
<nav class="mobile-bottom-nav show" id="mobileBottomNav">
    <a href="<?php echo htmlspecialchars(vendor_url('/dashboard')); ?>" class="mobile-bottom-nav-item" id="navHome" title="Home">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="mobile-bottom-nav-item" id="navOrders" title="Orders">
        <i class="fas fa-receipt"></i>
        <span>Orders</span>
        <?php 
        if ($totalNonCompleteCount > 0): ?>
        <span class="mobile-nav-badge"><?php echo $totalNonCompleteCount; ?></span>
        <?php else: ?>
        <span class="mobile-nav-badge empty"></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>" class="mobile-bottom-nav-item" id="navProducts" title="Products">
        <i class="fas fa-box"></i>
        <span>Products</span>
        <?php 
        if ($productsCount > 0): ?>
        <span class="mobile-nav-badge"><?php echo $productsCount; ?></span>
        <?php else: ?>
        <span class="mobile-nav-badge empty"></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo htmlspecialchars(vendor_url('/payouts')); ?>" class="mobile-bottom-nav-item" id="navPayouts" title="Payouts">
        <i class="fas fa-money-bill"></i>
        <span>Payouts</span>
    </a>
    <a href="<?php echo htmlspecialchars(vendor_url('/account')); ?>" class="mobile-bottom-nav-item" id="navAccount" title="Account">
        <i class="fas fa-user"></i>
        <span>Account</span>
    </a>
</nav>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Mobile Navigation Script -->
<script>
    // Mobile bottom nav active state based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navItems = document.querySelectorAll('.mobile-bottom-nav-item');
        
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            // Remove active class from all items
            item.classList.remove('active');
            
            // Add active class to matching item
            if (currentPath.includes('/dashboard') && href.includes('/dashboard')) {
                item.classList.add('active');
            } else if (currentPath.includes('/orders') && href.includes('/orders') && !currentPath.includes('/products')) {
                item.classList.add('active');
            } else if (currentPath.includes('/products') && href.includes('/products')) {
                item.classList.add('active');
            } else if (currentPath.includes('/payouts') && href.includes('/payouts')) {
                item.classList.add('active');
            } else if (currentPath.includes('/account') && href.includes('/account')) {
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

</body>
</html>
                