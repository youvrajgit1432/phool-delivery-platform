<?php
// app/views/account/preferences.php

// Use PathConfig singleton for dynamic path management
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('preferences', 'Preferences') . " - Phool Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Check if language was changed and needs page reload
$language_changed = $_SESSION['language_changed'] ?? false;
if ($language_changed) {
    unset($_SESSION['language_changed']);
}

unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="account-container">
    <div class="account-header" data-aos="fade-up" data-aos-delay="100">
        <h2><?= LanguageHelper::t('preferences', 'Preferences') ?></h2>
        <p><?= LanguageHelper::t('customize_account_settings', 'Customize your account settings') ?></p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" data-aos="zoom-in" data-aos-delay="150"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error" data-aos="zoom-in" data-aos-delay="150"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="preferences-content">
        <form method="POST" action="<?= $pathConfig->url('account/preferences') ?>" class="preferences-form" id="preferencesForm" data-aos="zoom-in" data-aos-delay="200">
            <div class="form-section" data-aos="fade-up" data-aos-delay="250">
                <h3><?= LanguageHelper::t('language_region', 'Language & Region') ?></h3>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                    <label for="language"><?= LanguageHelper::t('language', 'Language') ?></label>
                    <select id="language" name="language" class="language-select">
                        <option value="en" <?php echo ($preferences['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>><?= LanguageHelper::t('english', 'English') ?></option>
                        <option value="ne" <?php echo ($preferences['language'] ?? 'en') === 'ne' ? 'selected' : ''; ?>><?= LanguageHelper::t('nepali', 'नेपाली (Nepali)') ?></option>
                    </select>
                    <p class="form-help"><?= LanguageHelper::t('language_change_warning', 'Changing language will reload the page to apply translations') ?></p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="savePreferencesBtn" data-aos="zoom-in" data-aos-delay="350">
                <?= LanguageHelper::t('save_preferences', 'Save Preferences') ?>
            </button>
                   <a href="<?= $pathConfig->url('account') ?>" 
   style="
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: 1px solid #333;
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
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if language was changed and reload page
    <?php if ($language_changed): ?>
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    <?php endif; ?>

    // Language change detection
    const languageSelect = document.getElementById('language');
    const originalLanguage = languageSelect.value;
    
    languageSelect.addEventListener('change', function() {
        const newLanguage = this.value;
        if (newLanguage !== originalLanguage) {
            // Show warning that page will reload
            if (confirm('<?= LanguageHelper::t('language_change_confirm', 'Changing language will reload the page. Continue?') ?>')) {
                // Form will submit and trigger page reload
            } else {
                // Reset to original language
                this.value = originalLanguage;
            }
        }
    });

    // Preferences form handling
    const preferencesForm = document.getElementById('preferencesForm');
    if (preferencesForm) {
        preferencesForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = '<?= LanguageHelper::t('saving', 'Saving...') ?>';
            
            // Let the form submit normally for preferences
            // The server will handle language changes and redirect
        });
    }
});
</script>