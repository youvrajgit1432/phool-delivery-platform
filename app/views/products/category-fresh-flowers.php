<?php
// app/views/products/category-fresh-flowers.php

// Set category-specific data
$category_id = 9; // Fresh Flowers category ID
$category_name = LanguageHelper::t('fresh_flowers', 'Fresh Flowers');

// Include the template
include __DIR__ . '/category-template.php';
?>