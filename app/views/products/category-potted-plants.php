<?php
// app/views/products/category-potted-plants.php

// Set category-specific data
$category_id = 13; // Potted Plants category ID
$category_name = LanguageHelper::t('potted_plants', 'Potted Plants');

// Include the template
include __DIR__ . '/category-template.php';
?>