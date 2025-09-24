<?php
// Override PHP upload limits at runtime
ini_set('upload_max_filesize', '25M');
ini_set('post_max_size', '30M');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '256M');
ini_set('max_input_time', '300');
ini_set('max_input_vars', '3000');
ini_set('file_uploads', '1');
ini_set('max_file_uploads', '20');

// Verify settings
$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => ini_get('file_uploads') ? 'On' : 'Off'
];

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'PHP upload limits have been set',
    'settings' => $settings
]);
?>
