<?php
// config/constants.php

// Define application constants
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/views');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Environment
define('ENVIRONMENT', 'development'); // Change to 'production' when live