<?php
/**
 * Session Configuration
 */

return [
    'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => STORAGE_PATH . '/sessions',
];
?>
