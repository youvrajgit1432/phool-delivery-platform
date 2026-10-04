<?php
// Get PathConfig instance
$pathConfig = PathConfig::getInstance();

$user = $user ?? null;
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="account-container">
           <a href="<?= $pathConfig->url('account') ?>" 
   style="
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: 1px solid #e6e2e2ff;
        border-radius: 6px;
        background-color: transparent;
        color: #333;
        font-size: 14px;
        text-decoration: none;
        transition: 0.2s ease-in-out;
   "
   onmouseover="this.style.backgroundColor='#827b7bff'; this.style.color='#fff';"
   onmouseout="this.style.backgroundColor='transparent'; this.style.color='#887979ff';"
>
    <i class="fas fa-arrow-left"></i> 
    <?= LanguageHelper::t('back_to_dashboard', 'Back') ?>
</a>
    <div class="account-content">
        <!-- SEO-optimized header with location and service keywords -->
        <div class="content-header" data-aos="fade-up" data-aos-delay="100">
            <div>
                <h1>Fresh Flower Delivery Service in Nepal - Phool, Fool & Ful Delivery</h1>
                <p class="welcome-text">24/7 Fresh Marigold, Sayapatri & Organic Flower Delivery for Tihar, Dashain, Weddings & All Occasions in Kathmandu, Banepa, Dhulikhel & Bhaktapur</p>
            </div>
        </div>
        
        <div class="content-body">
            <?php if ($success_message): ?>
                <div class="alert alert-success" data-aos="zoom-in" data-aos-delay="150"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error" data-aos="zoom-in" data-aos-delay="150"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- SEO-rich announcement with festival and location keywords -->
            <div class="interest-notification" data-aos="zoom-in" data-aos-delay="200">
                <h2><i class="fas fa-bullhorn"></i> Fresh Flower Delivery Expansion 2025 - Now Serving Kathmandu Valley!</h2>
                <p>We're excited to announce direct-to-customer <strong>phool delivery</strong> in <strong>Banepa, Dhulikhel, Bhaktapur, and Kathmandu</strong>! Available year-round for all occasions including <strong>Tihar decorations, Dashain puja flowers, wedding garlands, birthday bouquets, and funeral flowers</strong>. Starting with fresh <strong>marigold (sayapatri), rose (gulab), and organic flowers</strong> from local farms. We deliver to temples including <strong>Pashupatinath, Chandeshwori, Namobuddha, and Nyatapola</strong> for all your <strong>puja and festival needs</strong>.</p>
            </div>

            <div class="support-grid">
                <!-- Contact section with location-specific keywords -->
                <div class="support-card" data-aos="zoom-in" data-aos-delay="250">
                    <h2><i class="fas fa-phone-alt"></i> Flower Delivery Contact Information - Nepal</h2>
                    <p>Contact our <strong>fresh flower delivery team</strong> for <strong>same-day phool delivery</strong> in <strong>Kathmandu Valley, Banepa, Dhulikhel, and Bhaktapur</strong>. We specialize in <strong>marigold garlands, wedding decorations, Tihar flowers, and organic festival arrangements</strong>.</p>
                    
                    <ul class="contact-info">
                        <li data-aos="fade-right" data-aos-delay="300"><i class="fas fa-phone"></i><div class="contact-details"><span class="contact-label">Primary Flower Delivery Line</span><span class="contact-value">+977 9803962360</span></div></li>
                        <li data-aos="fade-right" data-aos-delay="350"><i class="fas fa-phone"></i><div class="contact-details"><span class="contact-label">Secondary Phool Delivery</span><span class="contact-value">+977 9844634579</span></div></li>
                        <li data-aos="fade-right" data-aos-delay="400"><i class="fas fa-envelope"></i><div class="contact-details"><span class="contact-label">Fresh Flower Inquiries</span><span class="contact-value">info@phooldelivery.example</span></div></li>
                        <li data-aos="fade-right" data-aos-delay="450"><i class="fas fa-envelope"></i><div class="contact-details"><span class="contact-label">Customer Flower Support</span><span class="contact-value">support@phooldelivery.example</span></div></li>
                        <li data-aos="fade-right" data-aos-delay="500"><i class="fas fa-map-marker-alt"></i><div class="contact-details"><span class="contact-label">Service Areas</span><span class="contact-value">Kathmandu, Banepa, Dhulikhel, Bhaktapur - Bulk & Retail</span></div></li>
                    </ul>

                    <div class="business-hours" data-aos="fade-up" data-aos-delay="550">
                        <h3>Flower Delivery Hours</h3>
                        <table class="hours-table">
                            <tr data-aos="fade-right" data-aos-delay="600"><td class="day">Monday - Friday</td><td class="time">7:00 AM - 9:00 PM</td></tr>
                            <tr data-aos="fade-right" data-aos-delay="650"><td class="day">Saturday</td><td class="time">8:00 AM - 8:00 PM</td></tr>
                            <tr data-aos="fade-right" data-aos-delay="700"><td class="day">Sunday</td><td class="time">9:00 AM - 6:00 PM</td></tr>
                            <tr data-aos="fade-right" data-aos-delay="750"><td class="day">Festival Seasons</td><td class="time">Extended hours for Tihar, Dashain</td></tr>
                        </table>
                    </div>
                </div>

                <!-- Quick support with action-oriented language -->
                <div class="support-card" data-aos="zoom-in" data-aos-delay="300">
                    <h2><i class="fas fa-headset"></i> Quick Flower Delivery Support</h2>
                    <p>Need <strong>same-day flower delivery</strong> for <strong>Tihar decorations, wedding garlands, or puja flowers</strong>? Contact us immediately:</p>
                    
                    <div style="margin-top: 20px;">
                        <a href="tel:+9779803962360" class="btn-primary" style="width: 100%; text-align: center; justify-content: center; margin-bottom: 10px;" data-aos="zoom-in" data-aos-delay="350"><i class="fas fa-phone"></i> Call for Urgent Flower Delivery</a>
                        <a href="tel:+9779844634579" class="btn-primary" style="width: 100%; text-align: center; justify-content: center; margin-bottom: 10px;" data-aos="zoom-in" data-aos-delay="400"><i class="fas fa-phone"></i> Call for Bulk Phool Orders</a>
                        <a href="mailto:support@phooldelivery.example" class="btn-primary" style="width: 100%; text-align: center; justify-content: center; background: #EA4335;" data-aos="zoom-in" data-aos-delay="450"><i class="fas fa-envelope"></i> Email Flower Requirements</a>
                    </div>

                    <!-- Additional SEO content block -->
                    <div class="seo-keywords" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;" data-aos="fade-up" data-aos-delay="500">
                        <h4>Popular Flower Services:</h4>
                        <p><strong>Marigold delivery</strong> | <strong>Sayapatri phool</strong> | <strong>Wedding decorations</strong> | <strong>Tihar flowers</strong> | <strong>Dashain garlands</strong> | <strong>Organic flowers</strong> | <strong>Puja samagri</strong> | <strong>Funeral flowers</strong> | <strong>Birthday bouquets</strong></p>
                    </div>
                </div>
            </div>

            <!-- Comprehensive FAQ with rich keyword targeting -->
            <div class="faq-section" data-aos="fade-up" data-aos-delay="300">
                <h2>Frequently Asked Questions - Flower Delivery Nepal</h2>
                
                <div class="faq-item" data-aos="zoom-in" data-aos-delay="350">
                    <div class="faq-question">What areas do you deliver flowers to in Nepal?<i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer">
                        <p>We currently deliver <strong>fresh flowers, phool, and ful</strong> throughout <strong>Kathmandu Valley including Banepa, Dhulikhel, and Bhaktapur</strong>. Our service covers:</p>
                        <ul>
                            <li><strong>Kathmandu:</strong> Pashupatinath, Boudhanath, Swayambhunath areas</li>
                            <li><strong>Banepa:</strong> Chandeshwori Temple, local communities</li>
                            <li><strong>Dhulikhel:</strong> Namobuddha, Kali Temple regions</li>
                            <li><strong>Bhaktapur:</strong> Nyatapola Temple, Durbar Square areas</li>
                        </ul>
                        <p>We're expanding based on demand - let us know your location!</p>
                    </div>
                </div>

                <div class="faq-item" data-aos="zoom-in" data-aos-delay="400">
                    <div class="faq-question">What types of flowers and decorations do you offer?<i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer">
                        <p>We specialize in <strong>fresh, organic flowers</strong> for all occasions:</p>
                        <ul>
                            <li><strong>Festival Flowers:</strong> Marigold (Sayapatri), Godawari, Rose for Tihar, Dashain, Holi</li>
                            <li><strong>Wedding Decorations:</strong> Garlands, mandap flowers, bouquet</li>
                            <li><strong>Puja Requirements:</strong> Fresh flowers for temple worship</li>
                            <li><strong>Special Occasions:</strong> Birthday flowers, anniversary bouquets</li>
                            <li><strong>Funeral Flowers:</strong> Respectful arrangements</li>
                            <li><strong>Decoration Items:</strong> Rangoli materials, toran, lighting</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item" data-aos="zoom-in" data-aos-delay="450">
                    <div class="faq-question">How can I order flowers for Tihar or other festivals?<i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer">
                        <p>Ordering <strong>festival flowers for Tihar, Dashain, or Holi</strong> is easy:</p>
                        <ol>
                            <li>Call our dedicated <strong>festival flower line</strong></li>
                            <li>Specify your needs: <strong>marigold garlands, sayapatri, decoration flowers</strong></li>
                            <li>Choose delivery location: <strong>home, temple, or event venue</strong></li>
                            <li>Select quantity: we offer <strong>2kg, 5kg, 10kg bulk options</strong></li>
                        </ol>
                        <p>We recommend booking <strong>festival flowers</strong> in advance for best availability.</p>
                    </div>
                </div>

                <div class="faq-item" data-aos="zoom-in" data-aos-delay="500">
                    <div class="faq-question">Do you provide organic and eco-friendly flowers?<i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer">
                        <p>Yes! We're committed to <strong>organic, eco-friendly flower delivery</strong>:</p>
                        <ul>
                            <li><strong>Locally grown flowers</strong> from Nepali farmers</li>
                            <li><strong>Chemical-free cultivation</strong> practices</li>
                            <li><strong>Biodegradable packaging</strong> and materials</li>
                            <li><strong>Plastic-free garlands</strong> and decorations</li>
                            <li>Support for <strong>sustainable farming</strong> in Nepal</li>
                        </ul>
                        <p>Choose us for <strong>fresh, organic phool delivery</strong> that's better for you and the environment.</p>
                    </div>
                </div>

                <div class="faq-item" data-aos="zoom-in" data-aos-delay="550">
                    <div class="faq-question">What are your bulk flower delivery options?<i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer">
                        <p>We specialize in <strong>bulk flower delivery</strong> for:</p>
                        <ul>
                            <li><strong>Weddings and large events</strong></li>
                            <li><strong>Temple decorations and pujas</strong></li>
                            <li><strong>Festival celebrations</strong> (Tihar, Dashain)</li>
                            <li><strong>Commercial establishments</strong></li>
                        </ul>
                        <p>Our <strong>bulk delivery options</strong> include competitive pricing, custom arrangements, and flexible delivery schedules. Contact us directly for <strong>wholesale flower prices</strong> and <strong>volume discounts</strong>.</p>
                    </div>
                </div>

                <!-- Additional SEO-rich FAQ items -->
                <div class="faq-item" data-aos="zoom-in" data-aos-delay="600">
                    <div class="faq-question">Do you deliver to temples like Pashupatinath and Chandeshwori?<i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer">
                        <p>Yes! We provide <strong>fresh flower delivery to major temples</strong> including:</p>
                        <ul>
                            <li><strong>Pashupatinath Temple</strong> - Kathmandu</li>
                            <li><strong>Chandeshwori Temple</strong> - Banepa</li>
                            <li><strong>Namobuddha Stupa</strong> - Dhulikhel</li>
                            <li><strong>Nyatapola Temple</strong> - Bhaktapur</li>
                            <li><strong>Boudhanath Stupa</strong> - Kathmandu</li>
                        </ul>
                        <p>We understand the importance of <strong>fresh, quality flowers for puja and worship</strong>.</p>
                    </div>
                </div>
            </div>

            <!-- Additional SEO content section -->
            <div class="seo-content-section" data-aos="fade-up" data-aos-delay="400">
                <h2>Fresh Flower Delivery Services Across Nepal</h2>
                
                <div class="service-grid">
                    <div class="service-card" data-aos="zoom-in" data-aos-delay="450">
                        <h3><i class="fas fa-place-of-worship"></i> Temple Flower Delivery</h3>
                        <p>Fresh flowers for <strong>Pashupatinath, Chandeshwori, Namobuddha, and other temples</strong>. Regular and bulk deliveries for <strong>daily puja and special ceremonies</strong>.</p>
                    </div>
                    
                    <div class="service-card" data-aos="zoom-in" data-aos-delay="500">
                        <h3><i class="fas fa-birthday-cake"></i> Occasion Flowers</h3>
                        <p>Beautiful arrangements for <strong>birthdays, anniversaries, weddings, and celebrations</strong>. <strong>Rose bouquets, mixed flowers, and custom designs</strong>.</p>
                    </div>
                    
                    <div class="service-card" data-aos="zoom-in" data-aos-delay="550">
                        <h3><i class="fas fa-leaf"></i> Organic & Festival Flowers</h3>
                        <p><strong>Eco-friendly marigold, sayapatri, and traditional flowers</strong> for <strong>Tihar, Dashain, Holi and all Nepali festivals</strong>. <strong>Direct from local farms</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        item.querySelector('.faq-question').addEventListener('click', function() {
            faqItems.forEach(otherItem => { 
                if (otherItem !== item) otherItem.classList.remove('active'); 
            });
            item.classList.toggle('active');
        });
    });

    // Additional functionality for phone number tracking
    const phoneLinks = document.querySelectorAll('a[href^="tel:"]');
    phoneLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Add analytics tracking here
            console.log('Phone link clicked: ' + this.getAttribute('href'));
        });
    });
});
</script>

<style>
/* Additional CSS for new elements */
.seo-keywords {
    font-size: 0.9em;
    line-height: 1.4;
}

.seo-keywords h4 {
    margin-bottom: 8px;
    color: #2c5530;
}

.service-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.service-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #4CAF50;
}

.service-card h3 {
    color: #2c5530;
    margin-bottom: 10px;
    font-size: 1.1em;
}

.service-card p {
    font-size: 0.9em;
    line-height: 1.5;
    color: #555;
}

/* Ensure proper heading hierarchy */
.content-header h1 {
    font-size: 1.8em;
    margin-bottom: 10px;
    color: #2c5530;
}

.content-header .welcome-text {
    font-size: 1.1em;
    line-height: 1.5;
}

h2 {
    color: #2c5530;
    margin: 20px 0 15px 0;
}

h3 {
    color: #2c5530;
    margin: 15px 0 10px 0;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .service-grid {
        grid-template-columns: 1fr;
    }
    
    .content-header h1 {
        font-size: 1.5em;
    }
}
</style>


  <style>
        :root {
            --primary: #FF6B00; --primary-light: #FF8C42; --primary-dark: #E55A00;
            --secondary: #1A1A1A; --secondary-light: #2D2D2D; --background: #FFFFFF;
            --card-bg: #F8F8F8; --text-primary: #1A1A1A; --text-secondary: #666666;
            --text-light: #999999; --border: #E0E0E0; --success: #10B981; --error: #EF4444;
            --warning: #F59E0B; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #F5F5F5; color: var(--text-primary); line-height: 1.6;
        }

        .account-container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        .account-content { background: var(--background); border-radius: 16px; box-shadow: var(--card-shadow); overflow: hidden; margin-bottom: 30px; }

        .content-header { padding: 25px 30px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; }

        .content-header h2 { margin: 0 0 5px 0; font-size: 1.8rem; font-weight: 700; }

        .welcome-text { opacity: 0.9; margin: 0; font-size: 1rem; }

        .content-body { padding: 30px; }

        .support-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px; }

        .support-card { background: var(--card-bg); padding: 25px; border-radius: 12px; border: 1px solid var(--border); transition: var(--transition); }

        .support-card:hover { transform: translateY(-5px); box-shadow: var(--card-shadow); border-color: var(--primary); }

        .support-card h3 { margin: 0 0 15px 0; color: var(--text-primary); font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }

        .support-card h3 i { color: var(--primary); font-size: 1.5rem; }

        .support-card p { color: var(--text-secondary); margin-bottom: 20px; line-height: 1.6; }

        .contact-info { list-style: none; margin: 20px 0; }

        .contact-info li { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; background: white; border-radius: 8px; border-left: 4px solid var(--primary); }

        .contact-info i { color: var(--primary); font-size: 1.2rem; width: 20px; }

        .contact-details { flex: 1; }

        .contact-label { font-weight: 600; color: var(--text-primary); display: block; }

        .contact-value { color: var(--text-secondary); font-size: 0.95rem; }

        .faq-section { margin: 40px 0; }

        .faq-section h2 { margin-bottom: 25px; color: var(--text-primary); font-size: 1.5rem; }

        .faq-item { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 15px; overflow: hidden; }

        .faq-question { padding: 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: var(--text-primary); transition: var(--transition); }

        .faq-question:hover { background: rgba(255, 107, 0, 0.05); }

        .faq-question i { color: var(--primary); transition: var(--transition); }

        .faq-answer { padding: 0 20px; max-height: 0; overflow: hidden; transition: var(--transition); color: var(--text-secondary); }

        .faq-item.active .faq-answer { padding: 0 20px 20px; max-height: 500px; }

        .faq-item.active .faq-question i { transform: rotate(180deg); }

        .btn-primary {
            background: var(--primary); color: white; padding: 12px 30px; border-radius: 8px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            font-weight: 600; transition: var(--transition); border: none; cursor: pointer; font-size: 1rem;
        }

        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(255, 107, 0, 0.3); }

        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }

        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }

        .alert-error { background: rgba(239, 68, 68, 0.1); color: var(--error); border: 1px solid rgba(239, 68, 68, 0.2); }

        .business-hours { margin-top: 20px; }

        .hours-table { width: 100%; border-collapse: collapse; margin-top: 10px; }

        .hours-table td { padding: 8px 0; border-bottom: 1px solid var(--border); }

        .hours-table tr:last-child td { border-bottom: none; }

        .day { font-weight: 600; color: var(--text-primary); }

        .time { text-align: right; color: var(--text-secondary); }

        .interest-notification { background: rgba(255, 107, 0, 0.1); border-left: 4px solid var(--primary); padding: 15px; margin: 20px 0; border-radius: 4px; }

        @media (max-width: 768px) {
            .account-container { padding: 15px; }
            .content-body { padding: 20px; }
            .support-grid { grid-template-columns: 1fr; }
            .contact-info li { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
    </style>