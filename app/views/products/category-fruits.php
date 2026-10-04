<?php
// app/views/products/category-fruits.php

// Set category-specific data
$category_id = 7; // Fruits category ID
$category_name = LanguageHelper::t('fruits', 'Fruits');

// Include the template
include __DIR__ . '/category-template.php';
?>
