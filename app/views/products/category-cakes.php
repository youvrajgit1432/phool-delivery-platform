<?php
// app/views/products/category-cakes.php

// Set category-specific data
$category_id = 11; // Cakes & Bakery Items category ID
$category_name = LanguageHelper::t('cakes_bakery', 'Cakes & Bakery Items');

// Include the template
include __DIR__ . '/category-template.php';
?>