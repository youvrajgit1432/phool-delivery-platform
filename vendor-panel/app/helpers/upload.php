<?php
/**
 * File Upload Helper Functions
 */

/**
 * Handle file upload
 */
function upload_file($file, $directory)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $filename = basename($file['name']);
    $filepath = UPLOADS_PATH . '/' . $directory . '/' . $filename;

    if (!is_dir(dirname($filepath))) {
        mkdir(dirname($filepath), 0755, true);
    }

    return move_uploaded_file($file['tmp_name'], $filepath);
}

/**
 * Get upload path
 */
function get_upload_path($path)
{
    return UPLOADS_PATH . '/' . $path;
}
?>
