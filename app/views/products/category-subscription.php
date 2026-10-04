<?php
// app/views/products/category-subscription.php

// Set category-specific data
$category_id = 17; // Subscription & Bulk Orders category ID
$category_name = LanguageHelper::t('subscription_bulk', 'Subscription & Bulk Orders');

// Include the template
include __DIR__ . '/category-template.php';
?>