<?php
// Simple upload test to diagnose 400 Bad Request issues
// Access via: http://localhost:8000/test-upload.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check PHP configuration
$config = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'file_uploads' => ini_get('file_uploads') ? 'On' : 'Off',
    'max_input_vars' => ini_get('max_input_vars'),
    'max_input_time' => ini_get('max_input_time'),
];

// Check if file was uploaded
if (!isset($_FILES['image'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No file uploaded',
        'post_data_size' => strlen(file_get_contents('php://input')),
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'unknown',
        'config' => $config,
        'post_vars' => count($_POST),
        'files_count' => count($_FILES)
    ]);
    exit();
}

$file = $_FILES['image'];

// Check upload errors
$upload_errors = [
    UPLOAD_ERR_OK => 'No error',
    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
];

$response = [
    'success' => $file['error'] === UPLOAD_ERR_OK,
    'file_info' => [
        'name' => $file['name'],
        'size' => $file['size'],
        'type' => $file['type'],
        'tmp_name' => $file['tmp_name'],
        'error' => $file['error'],
        'error_message' => $upload_errors[$file['error']] ?? 'Unknown error'
    ],
    'config' => $config,
    'server_info' => [
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'post_data_size' => strlen(file_get_contents('php://input'))
    ]
];

if ($file['error'] === UPLOAD_ERR_OK) {
    $response['message'] = 'Upload successful! Your server can handle this file size.';
} else {
    $response['message'] = 'Upload failed: ' . ($upload_errors[$file['error']] ?? 'Unknown error');
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
