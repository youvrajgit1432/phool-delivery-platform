<?php
// app/views/products/category-festival-specials.php

// Set category-specific data
$category_id = 16; // Festival Specials category ID
$category_name = LanguageHelper::t('festival_specials', 'Festival Specials');

// Include the template
include __DIR__ . '/category-template.php';
?>