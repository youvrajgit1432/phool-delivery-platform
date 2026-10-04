<?php
// app/views/products/category-gift-items.php

// Set category-specific data
$category_id = 15; // Gift Items (Add-ons) category ID
$category_name = LanguageHelper::t('gift_items', 'Gift Items (Add-ons)');

// Include the template
include __DIR__ . '/category-template.php';
?>