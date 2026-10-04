<?php
// app/views/products/category-vegetables.php

// Set category-specific data
$category_id = 8; // Vegetables category ID
$category_name = LanguageHelper::t('vegetables', 'Vegetables');

// Include the template
include __DIR__ . '/category-template.php';
?>