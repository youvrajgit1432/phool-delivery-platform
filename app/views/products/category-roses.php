<?php
// app/views/products/category-roses.php

// Set category-specific data
$category_id = 5; // Roses category ID
$category_name = LanguageHelper::t('roses', 'Roses');

// Include the template
include __DIR__ . '/category-template.php';
?>