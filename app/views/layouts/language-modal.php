<?php
// Always load the language modal in DOM, even if user has language preference set
// This allows users to change language at any time by clicking the language button
$currentLang = LanguageHelper::getCurrentLanguage();
?>
<div id="languageModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px; width: 90%; margin: auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative;">
        <button type="button" class="modal-close-btn" onclick="closeLanguageModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 28px; cursor: pointer; color: #666; z-index: 10; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-header" style="text-align: center; border-bottom: 1px solid #eee; padding: 20px 15px;">
            <h2 style="margin: 0; color: #2d5016; font-size: clamp(18px, 5vw, 24px);"><i class="fas fa-globe" style="margin-right: 8px; color: #4a7c20;"></i><?= LanguageHelper::t('choose_language', 'Choose Your Language') ?></h2>
            <p style="margin: 10px 0 0 0; color: #666; font-size: clamp(14px, 4vw, 16px);"><?= LanguageHelper::t('select_preferred_language', 'Please select your preferred language for the best experience.') ?></p>
        </div>
        
        <div class="modal-body" style="padding: 20px 15px;">
            <div class="language-options" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                <div class="language-option <?= $currentLang === 'en' ? 'selected' : '' ?>" 
                     style="display: flex; align-items: center; padding: 12px; border: 2px solid <?= $currentLang === 'en' ? '#2d5016' : '#e0e0e0' ?>; border-radius: 10px; cursor: pointer; transition: all 0.3s ease; background: <?= $currentLang === 'en' ? '#f8fff8' : 'white' ?>;" 
                     onclick="selectLanguage('en')" id="language-en">
                    <div style="width: 35px; height: 35px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-weight: bold; font-size: 14px;">
                        EN
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: bold; margin-bottom: 4px; font-size: clamp(14px, 4vw, 16px);"><?= LanguageHelper::t('english', 'English') ?></div>
                        <div style="font-size: 0.85em; color: #666;">English</div>
                    </div>
                </div>
                
                <div class="language-option <?= $currentLang === 'ne' ? 'selected' : '' ?>" 
                     style="display: flex; align-items: center; padding: 12px; border: 2px solid <?= $currentLang === 'ne' ? '#2d5016' : '#e0e0e0' ?>; border-radius: 10px; cursor: pointer; transition: all 0.3s ease; background: <?= $currentLang === 'ne' ? '#f8fff8' : 'white' ?>;" 
                     onclick="selectLanguage('ne')" id="language-ne">
                    <div style="width: 35px; height: 35px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-weight: bold; font-size: 14px;">
                        न
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: bold; margin-bottom: 4px; font-size: clamp(14px, 4vw, 16px);"><?= LanguageHelper::t('nepali', 'Nepali') ?></div>
                        <div style="font-size: 0.85em; color: #666;">नेपाली</div>
                    </div>
                </div>
            </div>
            
            <div class="remember-choice" style="margin-bottom: 20px;" hidden>
                <label style="display: flex; align-items: center; cursor: pointer; font-size: clamp(14px, 4vw, 16px);">
                    <input type="checkbox" id="rememberLanguage" style="margin-right: 10px; width: 18px; height: 18px;" checked>
                    <span><?= LanguageHelper::t('remember_choice', 'Remember my choice for future visits') ?></span>
                </label>
            </div>
            
            <div class="modal-actions" style="display: flex; gap: 10px; flex-direction: column;">
                <button type="button" onclick="saveLanguagePreference()" class="btn btn-primary" style="padding: 12px; background: #2d5016; color: white; border: none; border-radius: 8px; font-size: clamp(14px, 4vw, 16px); cursor: pointer;" id="saveLanguageBtn" <?= $currentLang ? '' : 'disabled' ?>>
                    <?= LanguageHelper::t('continue', 'Continue') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedLanguage = '<?= $currentLang ?>';

function selectLanguage(language) {
    selectedLanguage = language;
    
    // Update UI
    document.querySelectorAll('.language-option').forEach(option => {
        option.classList.remove('selected');
        option.style.borderColor = '#e0e0e0';
        option.style.background = 'white';
    });
    const selectedOption = document.getElementById('language-' + language);
    selectedOption.classList.add('selected');
    selectedOption.style.borderColor = '#2d5016';
    selectedOption.style.background = '#f8fff8';
    
    // Enable save button
    document.getElementById('saveLanguageBtn').disabled = false;
}

function saveLanguagePreference() {
    if (!selectedLanguage) return;
    
    const rememberChoice = document.getElementById('rememberLanguage').checked;
    const saveBtn = document.getElementById('saveLanguageBtn');
    
    // Show loading state
    saveBtn.disabled = true;
    saveBtn.innerHTML = 'Loading...';
    
    // Use PathConfig to get correct base URL
    const baseUrl = '<?php 
        $pathConfig = PathConfig::getInstance();
        echo $pathConfig->get("base_url");
    ?>';
    
    // Construct the correct API URL
    const apiUrl = baseUrl + '/api/language/set';
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            language: selectedLanguage,
            remember: rememberChoice
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Language preference saved successfully:', data);
            
            // Clear any previous modal closed flags
            sessionStorage.removeItem('languageModalExplicitlyClosed');
            sessionStorage.removeItem('cityModalExplicitlyClosed');
            localStorage.removeItem('languageModalClosed');
            
            // ALWAYS reload page after language change to update all content
            // This ensures the UI updates to show the new language everywhere
            setTimeout(function() {
                window.location.reload();
            }, 300); // Small delay to ensure API has finished processing
        } else {
            // Silent fail - don't log to console in production
            alert('Error saving language preference: ' + (data.message || 'Unknown error'));
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<?= LanguageHelper::t('continue', 'Continue') ?>';
        }
    })
    .catch(error => {
        // Silent fail - don't log to console in production
        alert('Error saving language preference. Please try again.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<?= LanguageHelper::t('continue', 'Continue') ?>';
    });
}

function skipLanguageSelection() {
    // Use PathConfig to get correct base URL
    const baseUrl = '<?php 
        $pathConfig = PathConfig::getInstance();
        echo $pathConfig->get("base_url");
    ?>';
    
    // Construct the correct API URL
    const apiUrl = baseUrl + '/api/language/skip';
    
    fetch(apiUrl, {
        method: 'POST'
    })
    .then(() => {
        closeLanguageModal();
    })
    .catch(error => {
        console.error('Error skipping language selection:', error);
        closeLanguageModal();
    });
}

function closeLanguageModal(explicitClose = false) {
    const modal = document.getElementById('languageModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        
        // If user clicked X button, set flag and auto-set default language
        if (explicitClose) {
            localStorage.setItem('languageModalClosed', 'true');
            sessionStorage.setItem('languageModalExplicitlyClosed', 'true');
            
            // Auto-set default language (English)
            autoSetDefaultLanguage();
        }
        
        // Trigger event to show city modal after language modal closes
        const event = new CustomEvent('languageModalClosed', {
            detail: { timestamp: Date.now() }
        });
        document.dispatchEvent(event);
    }
}

function autoSetDefaultLanguage() {
    // Make AJAX call to set default language
    $.ajax({
        url: window.location.origin + '/api/language/set',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            language: 'en', // Default to English
            remember: true
        }),
        success: function(response) {
            console.log('Default language set successfully:', response);
        },
        error: function(xhr, status, error) {
            console.error('Error setting default language:', error);
        }
    });
}

// Initialize with current language and handle modal display
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('languageModal');
    if (modal) {
        // Don't show modal immediately, wait for global timing manager
        
        // Close modal when clicking outside (without marking as explicitly closed)
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeLanguageModal(false);
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeLanguageModal();
            }
        });
    }
    
    if (selectedLanguage) {
        selectLanguage(selectedLanguage);
    }
});
</script>

<style>
#languageModal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 999999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.language-option.selected {
    border-color: #2d5016 !important;
    background: #f8fff8 !important;
}

.language-option:hover {
    border-color: #4a7c20 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Mobile-specific styles */
@media (max-width: 480px) {
    #languageModal .modal-content {
        width: 95%;
        margin: 10px;
        max-width: none;
    }
    
    #languageModal .modal-body {
        padding: 15px 10px;
    }
    
    #languageModal .language-options {
        gap: 8px;
    }
    
    #languageModal .language-option {
        padding: 10px;
    }
    
    .modal-actions {
        gap: 8px !important;
    }
    
    .modal-actions button {
        padding: 10px !important;
    }
}

/* Tablet styles */
@media (max-width: 768px) {
    #languageModal .modal-content {
        width: 85%;
    }
}

/* Prevent body scroll when modal is open */
body.modal-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
    height: 100%;
}
</style>