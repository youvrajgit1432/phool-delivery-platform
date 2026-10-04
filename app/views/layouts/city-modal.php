<?php
// app/views/layouts/city-modal.php

// ENHANCED: Check if we should show the city modal with proper persistence
$shouldShowCityModal = false;

// Only show city modal if:
// 1. City preference is not set OR user is not logged in AND preference not set
// 2. AND it's not an API request
// 3. AND we're not in an iframe/AJAX request
if ((!isset($_SESSION['city_preference_set']) || $_SESSION['city_preference_set'] !== true) &&
    stripos($_SERVER['REQUEST_URI'], '/api/') === false &&
    empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    
    $shouldShowCityModal = true;
}

if ($shouldShowCityModal):
    // Get available cities from database
    $available_cities = [];
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT id, city_name, standard_delivery_fee FROM delivery_cities WHERE standard_delivery_fee >= 0 ORDER BY city_name");
        $stmt->execute();
        $available_cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching cities: " . $e->getMessage());
        // Fallback cities
        $available_cities = [
            ['id' => 1, 'city_name' => 'Banepa', 'standard_delivery_fee' => 0],
            ['id' => 2, 'city_name' => 'Kathmandu', 'standard_delivery_fee' => 100],
            ['id' => 3, 'city_name' => 'Bhaktapur', 'standard_delivery_fee' => 100],
            ['id' => 4, 'city_name' => 'Pokhara', 'standard_delivery_fee' => 100]
        ];
    }
    
    // Get current city preference from session or default to Kathmandu
    $current_city_id = $_SESSION['user_city_id'] ?? 2; // Default to Kathmandu (ID 2)
    $current_city_name = $_SESSION['user_city_name'] ?? 'Kathmandu';
    
    // Ensure Kathmandu is selected by default if no city is set
    if (!isset($_SESSION['user_city_id']) && !empty($available_cities)) {
        $kathmandu_city = null;
        foreach ($available_cities as $city) {
            if ($city['city_name'] === 'Kathmandu') {
                $kathmandu_city = $city;
                break;
            }
        }
        if ($kathmandu_city) {
            $current_city_id = $kathmandu_city['id'];
            $current_city_name = $kathmandu_city['city_name'];
        }
    }
    
    // Group cities into rows of 2
    $city_rows = [];
    for ($i = 0; $i < count($available_cities); $i += 2) {
        $city_rows[] = array_slice($available_cities, $i, 2);
    }
?>
<!-- FIXED: Added higher z-index and fixed event handlers -->
<div id="cityModal" class="modal" style="display: none; z-index: 999998;">
    <div class="modal-content" style="max-width: 500px; width: 90%; margin: auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); max-height: 90vh; display: flex; flex-direction: column; position: relative; z-index: 999997;">
        <button type="button" class="modal-close-btn" onclick="closeCityModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 28px; cursor: pointer; color: #666; z-index: 10; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-header" style="text-align: center; border-bottom: 1px solid #eee; padding: 20px 15px; flex-shrink: 0;">
            <h2 style="margin: 0; color: #2d5016; font-size: clamp(18px, 5vw, 24px);">📍 <?= LanguageHelper::t('select_delivery_city', 'Select Your Delivery City') ?></h2>
            <p style="margin: 10px 0 0 0; color: #666; font-size: clamp(14px, 4vw, 16px);"><?= LanguageHelper::t('select_city_for_pricing', 'Please select your city to see accurate pricing and delivery options.') ?></p>
        </div>
        
        <div class="modal-body" style="padding: 20px 15px; overflow-y: auto; flex: 1;">
            <div class="city-options" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                <?php foreach ($city_rows as $row_cities): ?>
                    <div class="city-row" style="display: flex; gap: 12px;">
                        <?php foreach ($row_cities as $city): ?>
                            <div class="city-option <?= $current_city_id == $city['id'] ? 'selected' : '' ?>" 
                                 style="flex: 1; display: flex; align-items: center; padding: 12px; border: 2px solid <?= $current_city_id == $city['id'] ? '#2d5016' : '#e0e0e0' ?>; border-radius: 10px; cursor: pointer; transition: all 0.3s ease; background: <?= $current_city_id == $city['id'] ? '#f8fff8' : 'white' ?>;" 
                                 data-city-id="<?= $city['id'] ?>" 
                                 data-city-name="<?= htmlspecialchars($city['city_name']) ?>" 
                                 data-delivery-fee="<?= $city['standard_delivery_fee'] ?>">
                                <div style="width: 35px; height: 35px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-weight: bold; font-size: 14px;">
                                    📍
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: bold; margin-bottom: 4px; font-size: clamp(14px, 4vw, 16px);"><?= htmlspecialchars($city['city_name']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php // Add empty placeholder if last row has only 1 city ?>
                        <?php if (count($row_cities) === 1): ?>
                            <div style="flex: 1; visibility: hidden;"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="remember-choice" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; cursor: pointer; font-size: clamp(14px, 4vw, 16px);">
                    <input type="checkbox" id="rememberCity" style="margin-right: 10px; width: 18px; height: 18px;" checked>
                    <span><?= LanguageHelper::t('remember_my_city', 'Remember my city for future visits') ?></span>
                </label>
            </div>
        </div>
        
       <div class="modal-footer" 
     style="padding: 15px 15px 20px 15px; 
            border-top: 1px solid #eee; 
            flex-shrink: 0;
            display: flex;
            justify-content: center;">
    
    <div class="modal-actions" 
         style="display: flex; 
                justify-content: center; 
                width: 100%;">
        
        <button type="button" class="btn btn-primary"
            style="padding: 12px; 
                   background: #2d5016; 
                   color: white; 
                   border: none; 
                   border-radius: 8px; 
                   font-size: clamp(14px, 4vw, 16px); 
                   cursor: pointer; 
                   font-weight: bold; 
                   width: 100%; 
                   max-width: 300px;"
            id="saveCityBtn"
            <?= $current_city_id ? '' : 'disabled' ?>>
            <?= LanguageHelper::t('continue', 'Continue') ?>
        </button>

    </div>
</div>

    </div>
</div>

<style>
/* FIXED: Enhanced modal styles for city modal */
#cityModal .modal-content {
    animation: modalSlideIn 0.3s ease-out;
}

.city-option {
    transition: all 0.3s ease;
}

.city-option:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.city-option.selected {
    border-color: #2d5016 !important;
    background: #f8fff8 !important;
    box-shadow: 0 4px 12px rgba(45, 80, 22, 0.2);
}
</style>

<script>
// FIXED: Enhanced JavaScript with proper event delegation and z-index management
let selectedCityId = <?= $current_city_id ?: 'null' ?>;
let selectedCityName = '<?= $current_city_name ?>';
let selectedDeliveryFee = 0;

function selectCity(cityId, cityName, deliveryFee) {
    selectedCityId = cityId;
    selectedCityName = cityName;
    selectedDeliveryFee = deliveryFee;
    
    // Update UI
    document.querySelectorAll('.city-option').forEach(option => {
        option.classList.remove('selected');
        option.style.borderColor = '#e0e0e0';
        option.style.background = 'white';
    });
    const selectedOption = document.querySelector(`[data-city-id="${cityId}"]`);
    if (selectedOption) {
        selectedOption.classList.add('selected');
        selectedOption.style.borderColor = '#2d5016';
        selectedOption.style.background = '#f8fff8';
    }
    
    // Enable save button
    const saveBtn = document.getElementById('saveCityBtn');
    if (saveBtn) {
        saveBtn.disabled = false;
    }
}

function saveCityPreference() {
    if (!selectedCityId) return;
    
    const rememberChoice = document.getElementById('rememberCity').checked;
    const saveBtn = document.getElementById('saveCityBtn');
    
    // Show loading state
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<?= LanguageHelper::t('saving', 'Saving...') ?>';
    
    // Use PathConfig to get correct base URL
    const baseUrl = '<?php 
        $pathConfig = PathConfig::getInstance();
        echo $pathConfig->get("base_url");
    ?>';
    
    // Construct the correct API URL
    const apiUrl = baseUrl + '/api/city/set';
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            city_id: selectedCityId,
            city_name: selectedCityName,
            delivery_fee: selectedDeliveryFee,
            remember: rememberChoice
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI elements without page refresh
            updateCityDisplay(selectedCityName, selectedDeliveryFee);
            
            // Close modal without reloading page
            closeCityModal();
            
            // Show success message
            showCitySelectionSuccess(selectedCityName);
            
        } else {
            alert('<?= LanguageHelper::t('error_saving_city', 'Error saving city preference') ?>: ' + data.message);
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<?= LanguageHelper::t('continue', 'Continue') ?>';
        }
    })
    .catch(error => {
        // Silent fail - don't log to console in production
        alert('<?= LanguageHelper::t('error_saving_city', 'Error saving city preference') ?>');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<?= LanguageHelper::t('continue', 'Continue') ?>';
    });
}

function updateCityDisplay(cityName, deliveryFee) {
    // Update any city display elements on the page
    const cityDisplayElements = document.querySelectorAll('[data-city-display]');
    cityDisplayElements.forEach(element => {
        element.textContent = cityName;
    });
    
    // Update delivery fee displays
    const deliveryFeeElements = document.querySelectorAll('[data-delivery-fee]');
    deliveryFeeElements.forEach(element => {
        element.textContent = 'Rs. ' + deliveryFee;
    });
    
    // Update any hidden form fields
    const cityIdInputs = document.querySelectorAll('input[name="city_id"], input[name="user_city_id"]');
    cityIdInputs.forEach(input => {
        input.value = selectedCityId;
    });
    
    // Trigger custom event for other scripts to listen to
    const event = new CustomEvent('cityChanged', {
        detail: {
            cityId: selectedCityId,
            cityName: cityName,
            deliveryFee: deliveryFee
        }
    });
    document.dispatchEvent(event);
}

function showCitySelectionSuccess(cityName) {
    // Create a temporary success message
    const successMsg = document.createElement('div');
    successMsg.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #2d5016;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 1000000;
        font-size: 14px;
        animation: slideInRight 0.3s ease;
    `;
    successMsg.innerHTML = `✅ <?= LanguageHelper::t('city_set_success', 'Delivery city set to') ?>: ${cityName}`;
    
    document.body.appendChild(successMsg);
    
    // Remove after 3 seconds
    setTimeout(() => {
        successMsg.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (successMsg.parentNode) {
                successMsg.parentNode.removeChild(successMsg);
            }
        }, 300);
    }, 3000);
}

function skipCitySelection() {
    // Use PathConfig to get correct base URL
    const baseUrl = '<?php 
        $pathConfig = PathConfig::getInstance();
        echo $pathConfig->get("base_url");
    ?>';
    
    // Construct the correct API URL
    const apiUrl = baseUrl + '/api/city/skip';
    
    fetch(apiUrl, {
        method: 'POST'
    })
    .then(() => {
        closeCityModal();
    })
    .catch(error => {
        console.error('Error skipping city selection:', error);
        closeCityModal();
    });
}

function closeCityModal(explicitClose = false) {
    const modal = document.getElementById('cityModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        
        // If user clicked X button, set flag and auto-set default city
        if (explicitClose) {
            localStorage.setItem('cityModalClosed', 'true');
            sessionStorage.setItem('cityModalExplicitlyClosed', 'true');
            
            // Auto-set default city (Kathmandu)
            autoSetDefaultCity();
        }
        
        // Trigger event when city modal closes
        const event = new CustomEvent('cityModalClosed', {
            detail: { timestamp: Date.now() }
        });
        document.dispatchEvent(event);
        
        // Remove modal from DOM after animation
        setTimeout(() => {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        }, 300);
    }
}

function autoSetDefaultCity() {
    // Make AJAX call to set default city
    $.ajax({
        url: window.location.origin + '/api/city/set',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            city_id: 2,
            city_name: 'Kathmandu',
            delivery_fee: 100,
            remember: true
        }),
        success: function(response) {
            console.log('Default city set successfully:', response);
            updateCityDisplay('Kathmandu', 100);
        },
        error: function(xhr, status, error) {
            console.error('Error setting default city:', error);
        }
    });
}

// FIXED: Enhanced initialization with proper event delegation
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('cityModal');
    
    if (modal) {
        // Don't show modal immediately, wait for global timing manager
        
        // Auto-select Kathmandu by default if no city is selected
        if (!selectedCityId && <?= !empty($available_cities) ? 'true' : 'false' ?>) {
            // Find Kathmandu in available cities
            const kathmanduCity = <?= json_encode($available_cities) ?>.find(city => city.city_name === 'Kathmandu');
            if (kathmanduCity) {
                selectCity(kathmanduCity.id, kathmanduCity.city_name, kathmanduCity.standard_delivery_fee);
            }
        }
        
        // FIXED: Use event delegation for all modal interactions
        modal.addEventListener('click', function(e) {
            // Close modal when clicking outside (without marking as explicitly closed)
            if (e.target === modal) {
                closeCityModal(false);
                return;
            }
            
            // Handle city option clicks
            const cityOption = e.target.closest('.city-option');
            if (cityOption) {
                const cityId = cityOption.getAttribute('data-city-id');
                const cityName = cityOption.getAttribute('data-city-name');
                const deliveryFee = cityOption.getAttribute('data-delivery-fee');
                selectCity(cityId, cityName, deliveryFee);
                return;
            }
            
            // Handle save button click
            if (e.target.id === 'saveCityBtn' || e.target.closest('#saveCityBtn')) {
                saveCityPreference();
                return;
            }
        });
        
        // FIXED: Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeCityModal();
            }
        });
    }
    
    if (selectedCityId) {
        selectCity(selectedCityId, selectedCityName, selectedDeliveryFee);
    }
});

// Add CSS animations for success message
const style = document.createElement('style');
style.textContent = `
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

// FIXED: Global function to show city modal if needed
function showCityModalIfNeeded() {
    const modal = document.getElementById('cityModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.classList.add('modal-open');
    }
}
</script>
<?php endif; ?>

<style>
#cityModal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.city-option.selected {
    border-color: #2d5016 !important;
    background: #f8fff8 !important;
}

.city-option:hover {
    border-color: #4a7c20 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Modal content scrollable area */
.modal-body {
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 transparent;
}

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
    background-color: #c1c1c1;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background-color: #a8a8a8;
}

/* Mobile-specific styles */
@media (max-width: 480px) {
    #cityModal .modal-content {
        width: 95%;
        margin: 10px;
        max-width: none;
        max-height: 85vh;
    }
    
    #cityModal .modal-body {
        padding: 15px 10px;
    }
    
    #cityModal .city-options {
        gap: 8px;
    }
    
    #cityModal .city-row {
        gap: 8px !important;
    }
    
    #cityModal .city-option {
        padding: 10px;
    }
    
    .modal-actions {
        gap: 8px !important;
    }
    
    .modal-actions button {
        padding: 10px !important;
    }
    
    .modal-footer {
        padding: 12px 10px 15px 10px !important;
    }
}

/* Tablet styles */
@media (max-width: 768px) {
    #cityModal .modal-content {
        width: 85%;
    }
}

/* Small height devices (like iPhone SE) */
@media (max-height: 700px) {
    #cityModal .modal-content {
        max-height: 80vh;
    }
    
    #cityModal .modal-header {
        padding: 15px 15px 10px 15px !important;
    }
    
    #cityModal .modal-body {
        padding: 10px 15px !important;
    }
    
    .city-options {
        gap: 8px !important;
    }
    
    .city-option {
        padding: 8px 10px !important;
    }
}

/* Extra small height devices */
@media (max-height: 600px) {
    #cityModal .modal-content {
        max-height: 75vh;
    }
    
    #cityModal .modal-header h2 {
        font-size: 18px !important;
    }
    
    #cityModal .modal-header p {
        font-size: 14px !important;
        margin: 5px 0 0 0 !important;
    }
}

/* Ensure proper layout for city rows */
.city-row {
    display: flex;
    gap: 12px;
    width: 100%;
}

.city-option {
    flex: 1;
    min-width: 0; /* Prevent flex items from overflowing */
}
</style>