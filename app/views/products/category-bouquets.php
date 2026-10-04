<?php
// app/views/products/category-bouquets.php

// Set category-specific data
$category_id = 10; // Bouquets & Arrangements category ID
$category_name = LanguageHelper::t('bouquets_arrangements', 'Bouquets & Arrangements');

// Include the template
include __DIR__ . '/category-template.php';
?>