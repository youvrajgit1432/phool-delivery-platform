<?php
// app/views/products/category-seeds.php

// Set category-specific data
$category_id = 14; // Seeds & Gardening Supplies category ID
$category_name = LanguageHelper::t('seeds_gardening', 'Seeds & Gardening Supplies');

// Include the template
include __DIR__ . '/category-template.php';
?>