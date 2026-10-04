<?php
/**
 * Link Master Products to Vendor - Vendor Panel
 * This allows vendors to link to master products (from products table)
 * instead of creating duplicate products
 */

// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

// Get all master products from products table
$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$masterProducts = [];
$linkedProductIds = [];
$vendorId = $_SESSION['vendor_id'] ?? null;

// Get vendor's already linked products
if ($vendorId) {
    try {
        $linkedQuery = "SELECT DISTINCT product_id FROM vendor_product_map 
                        WHERE vendor_id = ? AND unlinked_at IS NULL";
        $linkedResult = $db->query($linkedQuery, [$vendorId]);
        if ($linkedResult) {
            while ($row = $linkedResult->fetch(\PDO::FETCH_ASSOC)) {
                $linkedProductIds[] = $row['product_id'];
            }
        }
    } catch (\Exception $e) {
        error_log('Error fetching linked products: ' . $e->getMessage());
    }
}

try {
    // Query master products with category info
    $query = "SELECT p.id, p.name_en, p.category_id, p.price, p.bulk_price, 
                     p.unit
              FROM products p
              WHERE p.status = 'active'
              ORDER BY p.name_en ASC";
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $masterProducts[] = $row;
        }
    }
} catch (\Exception $e) {
    error_log('Error fetching master products: ' . $e->getMessage());
    $masterProducts = [];
}

// Sort products - unlinked first, then linked
usort($masterProducts, function($a, $b) use ($linkedProductIds) {
    $aLinked = in_array($a['id'], $linkedProductIds);
    $bLinked = in_array($b['id'], $linkedProductIds);
    return $aLinked <=> $bLinked;
});
?>


<div class="product-form-container">
    <div class="product-form-card">
        <!-- Form Header -->
        <div class="mb-4">
            <h2 class="mb-2">
                <i class="fas fa-link text-primary me-2"></i>Link Master Product
            </h2>
            <p class="text-muted mb-0">Select a product from our master catalog and enter your supply cost. Phool Delivery manages final customer pricing.</p>
        </div>

        <!-- Product Form -->
        <form id="productForm" method="POST" action="<?php echo htmlspecialchars(vendor_url('/ajax/link-master-product')); ?>" enctype="multipart/form-data">
            <?php if (isset($_SESSION) && !empty($_SESSION['vendor_id'])): ?>
                <input type="hidden" name="vendor_id" value="<?php echo htmlspecialchars($_SESSION['vendor_id']); ?>">
            <?php endif; ?>
            
            <!-- Master Product Selection Section -->
            <div class="form-section-header">
                <i class="fas fa-box"></i>
                Select Master Product
            </div>

            <div class="form-group">
                <label for="masterProduct" class="form-label">
                    <i class="fas fa-search me-2"></i>Master Product <span class="text-danger">*</span>
                </label>
                <select class="form-control" id="masterProduct" name="product_id" required>
                    <option value="">-- Select a product --</option>
                    <?php if (!empty($masterProducts)): ?>
                        <?php foreach ($masterProducts as $product): ?>
                            <?php 
                                $isLinked = in_array($product['id'], $linkedProductIds);
                                $optionText = htmlspecialchars($product['name_en']) . ' (Standard Quality – Unit: ' . $product['unit'] . ')';
                                if ($isLinked) {
                                    $optionText .= ' ✓ LINKED';
                                }
                            ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    data-price="<?php echo $product['price']; ?>"
                                    data-bulk-price="<?php echo $product['bulk_price'] ?? ''; ?>"
                                    data-unit="<?php echo $product['unit']; ?>"
                                    data-linked="<?php echo $isLinked ? 'true' : 'false'; ?>">
                                <?php echo $optionText; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No master products available. Please contact admin.</option>
                    <?php endif; ?>
                </select>
                <small class="form-help-text">Choose from our master product catalog. Multiple vendors can supply the same product. Final customer price is set by Phool Delivery.</small>
            </div>

            <!-- Product Details Display (Read-only from Master) -->
            <div class="alert alert-info d-none" id="productDetailsAlert">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Standard Quality:</strong> <span id="standardQuality">Standard</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Unit:</strong> <span id="masterUnit">-</span>
                    </div>
                    <div class="col-12 mt-2" id="priceGuidanceContainer">
                        <small id="priceGuidance" class="text-muted">Based on current demand and quality standard, suppliers typically deliver within a profitable cost range. This is guidance only.</small>
                        <div id="priceRange" class="mt-1" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Your Pricing & Inventory Section -->
            <div class="form-section-header mt-4">
                <i class="fas fa-dollar-sign"></i>
                Your Pricing & Inventory
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="vendorPrice" class="form-label">
                        <i class="fas fa-tag me-2"></i>Your Supply Price (₹) <span class="text-danger">*</span>
                    </label>
                          <input type="number" class="form-control" id="vendorPrice" name="vendor_price" 
                                    placeholder="0.00" step="0.01" min="0" required>
                                <small class="form-help-text">Enter the price you will supply this product at. Final customer price is managed by Phool Delivery.</small>
                                <div id="priceWarning" class="mt-2 text-danger" style="display:none;"></div>
                </div>

                <div class="form-group">
                    <label for="vendorStock" class="form-label">
                        <i class="fas fa-boxes me-2"></i>Your Available Quantity <span class="text-danger">*</span>
                    </label>
                    <input type="number" class="form-control" id="vendorStock" name="vendor_stock" 
                           placeholder="0" min="0" required>
                    <small class="form-help-text">How much quantity you can supply</small>
                </div>
            </div>

            <!-- Optional Details -->
            <div class="form-row">
                <div class="form-group">
                    <label for="prepTime" class="form-label">
                        <i class="fas fa-clock me-2"></i>Preparation Time
                    </label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="prepTime" name="preparation_time" 
                               placeholder="30" min="0" value="30">
                        <span class="input-group-text">minutes</span>
                    </div>
                    <small class="form-help-text">How long it takes to prepare this product</small>
                </div>

                <div class="form-group">
                    <label for="minOrder" class="form-label">
                        <i class="fas fa-box-open me-2"></i>Minimum Order Quantity
                    </label>
                    <input type="number" class="form-control" id="minOrder" name="minimum_order_quantity" 
                           placeholder="1" step="0.1" min="0.1" value="1">
                    <small class="form-help-text">Minimum quantity per order</small>
                </div>
            </div>

            <!-- Vendor Images Section -->
            <div class="form-section-header mt-4">
                <i class="fas fa-images"></i>
                Your Product Images (Optional)
            </div>

            <div class="image-upload-section">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> Master product already has images. Upload your own images if you want to showcase the product differently 
                    (different styling, packaging, or angles). These will be displayed to customers if available.
                </div>
                
                <label class="form-label d-block mb-2">
                    <i class="fas fa-cloud-upload-alt me-2"></i>Product Images
                </label>
                
                <div class="image-upload-wrapper" id="imageUploadArea">
                    <div class="image-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="image-upload-text">Click or drag images here</div>
                    <div class="image-upload-subtext">PNG, JPG or JPEG (max. 5MB each)</div>
                    <input type="file" id="imageInput" name="vendor_images[]" multiple accept="image/*" 
                           class="image-upload-input">
                </div>

                <div class="image-preview-container" id="imagePreviewContainer"></div>
                <small class="form-help-text d-block mt-2">Upload optional images. First image will be your primary image, rest will be gallery.</small>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-link me-2"></i>Link Product
                </button>
                <a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>" class="btn btn-cancel">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const masterProductSelect = document.getElementById('masterProduct');
    const vendorPriceInput = document.getElementById('vendorPrice');
    const vendorStockInput = document.getElementById('vendorStock');
    const productDetailsAlert = document.getElementById('productDetailsAlert');
    
    // Image upload
    const imageUploadArea = document.getElementById('imageUploadArea');
    const imageInput = document.getElementById('imageInput');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const maxFiles = 5;
    const maxSize = 5 * 1024 * 1024; // 5MB

    // Current guidance range (populated when product selected)
    let currentGuidanceMin = null;
    let currentGuidanceMax = null;

    // Click to upload
    imageUploadArea.addEventListener('click', () => imageInput.click());

    // Drag and drop
    imageUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        imageUploadArea.classList.add('drag-over');
    });

    imageUploadArea.addEventListener('dragleave', () => {
        imageUploadArea.classList.remove('drag-over');
    });

    imageUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        imageUploadArea.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });

    // File input change
    imageInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        const filesArray = Array.from(files);
        
        if (filesArray.length > maxFiles) {
            showToast(`Maximum ${maxFiles} images allowed`, 'warning');
            return;
        }

        filesArray.forEach((file, index) => {
            if (file.size > maxSize) {
                showToast(`File ${file.name} is too large (max 5MB)`, 'warning');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
                preview.dataset.imageIndex = index;
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <div class="image-badge" ${index === 0 ? '' : 'style="display:none;"'}>
                        <span class="badge bg-warning text-dark">Primary</span>
                    </div>
                    <button type="button" class="image-remove-btn" onclick="removeImagePreview(this);">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                imagePreviewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    }

    // When master product is selected, show unit and guidance; do NOT expose master/customer price
    masterProductSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const isLinked = selectedOption.dataset.linked === 'true';

        if (this.value === '') {
            productDetailsAlert.classList.add('d-none');
            vendorPriceInput.value = '';
            vendorStockInput.value = '';
            document.getElementById('priceRange').style.display = 'none';
            return;
        }

        // Show linked product warning if already linked
        if (isLinked) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning alert-dismissible fade show mt-2';
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
                <i class="fas fa-link me-2"></i>
                <strong>Already Linked:</strong> You have already linked this product. You can link it again with different pricing/stock if needed.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            const existingAlert = document.getElementById('linkedProductAlert');
            if (existingAlert) {
                existingAlert.remove();
            }
            alertDiv.id = 'linkedProductAlert';
            masterProductSelect.parentElement.appendChild(alertDiv);
        } else {
            const existingAlert = document.getElementById('linkedProductAlert');
            if (existingAlert) {
                existingAlert.remove();
            }
        }

        // Show product unit
        document.getElementById('masterUnit').textContent = selectedOption.dataset.unit || '-';

        // Show guidance block
        productDetailsAlert.classList.remove('d-none');

        // If guidance min/max data attributes exist, show range (these attributes can be added server-side later)
        const guidanceMin = selectedOption.dataset.guidanceMin;
        const guidanceMax = selectedOption.dataset.guidanceMax;
        const priceRangeDiv = document.getElementById('priceRange');
        if (guidanceMin && guidanceMax) {
            priceRangeDiv.style.display = 'block';
            priceRangeDiv.innerHTML = `<strong>Suggested supplier cost range:</strong> ₹${parseFloat(guidanceMin).toFixed(2)} - ₹${parseFloat(guidanceMax).toFixed(2)}`;
            currentGuidanceMin = parseFloat(guidanceMin);
            currentGuidanceMax = parseFloat(guidanceMax);
        } else {
            priceRangeDiv.style.display = 'none';
            priceRangeDiv.innerHTML = '';
            currentGuidanceMin = null;
            currentGuidanceMax = null;
        }

        // Do not pre-fill supply price with master/customer price. Leave to vendor to enter.
        vendorPriceInput.value = '';
        vendorPriceInput.focus();
    });

    // Soft validation: show a non-blocking warning when vendor price is outside guidance range
    vendorPriceInput.addEventListener('input', function() {
        const warningDiv = document.getElementById('priceWarning');
        const val = parseFloat(this.value);
        if (!val || !currentGuidanceMin || !currentGuidanceMax) {
            warningDiv.style.display = 'none';
            warningDiv.textContent = '';
            return;
        }

        if (val < currentGuidanceMin) {
            warningDiv.style.display = 'block';
            warningDiv.textContent = `Warning: entered supply price is below the suggested minimum (₹${currentGuidanceMin.toFixed(2)}). You can save anyway.`;
            return;
        }

        if (val > currentGuidanceMax) {
            warningDiv.style.display = 'block';
            warningDiv.textContent = `Warning: entered supply price is above the suggested maximum (₹${currentGuidanceMax.toFixed(2)}). You can save anyway.`;
            return;
        }

        warningDiv.style.display = 'none';
        warningDiv.textContent = '';
    });

    // Form submission
    document.getElementById('productForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate
        if (!masterProductSelect.value) {
            showToast('Please select a master product', 'warning');
            return;
        }

        if (!vendorPriceInput.value || vendorPriceInput.value <= 0) {
            showToast('Please enter a valid price', 'warning');
            return;
        }

        if (!vendorStockInput.value || vendorStockInput.value < 0) {
            showToast('Please enter a valid stock quantity', 'warning');
            return;
        }

        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Linking...';
        submitBtn.disabled = true;

        // Submit form
        const formData = new FormData(this);
        
        fetch(this.action.replace('.php', ''), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const contentType = response.headers.get('content-type') || '';
            const text = await response.text();

            // If server returned non-OK, try to parse JSON error message and surface it
            if (!response.ok) {
                if (contentType.includes('application/json')) {
                    try {
                        const errObj = JSON.parse(text);
                        const msg = errObj && errObj.message ? errObj.message : ('Server error: ' + response.status);
                        throw new Error(msg);
                    } catch (err) {
                        console.error('Invalid JSON in error response:', text);
                        throw new Error('Server error: ' + response.status + '. See console for details.');
                    }
                }

                console.error('Server returned non-OK status:', response.status, text);
                throw new Error('Server error: ' + response.status + '. See console for details.');
            }

            if (contentType.includes('application/json')) {
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Invalid JSON from server:', text);
                    throw new Error('Invalid JSON response from server. See console for details.');
                }
            }

            console.error('Expected JSON but received:', contentType, text);
            throw new Error('Unexpected server response (not JSON). See console for details.');
        })
        .then(data => {
            if (data.success) {
                showToast('Product linked successfully!', 'success');
                setTimeout(() => {
                    window.location.href = '<?php echo htmlspecialchars(vendor_url('/products')); ?>';
                }, 1500);
            } else {
                showToast('Error: ' + (data.message || 'Failed to link product'), 'error');
            }
        })
        .catch(error => {
            console.error('Link product error:', error);
            showToast(error.message.indexOf('See console') !== -1 ? 'Server error while linking product. Check console/Network for details.' : 'Error: ' + error.message, 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
});

function removeImagePreview(btn) {
    btn.parentElement.remove();
}
</script>
