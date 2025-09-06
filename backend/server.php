<?php
// Simple PHP development server router
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Handle API requests
if (strpos($path, '/api/') === 0) {
    // Remove /api from path and include api.php
    $_SERVER['REQUEST_URI'] = substr($request, 4);
    include 'api.php';
    return;
}

// Serve static files
$filePath = ltrim($path, '/');

// Default to index.html
if (empty($filePath) || $filePath === '/') {
    $filePath = 'index.html';
}

// Security check - prevent directory traversal
if (strpos($filePath, '..') !== false) {
    http_response_code(403);
    echo 'Access denied';
    return;
}

if (file_exists($filePath)) {
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    
    switch ($extension) {
        case 'html':
            header('Content-Type: text/html');
            break;
        case 'css':
            header('Content-Type: text/css');
            break;
        case 'js':
            header('Content-Type: application/javascript');
            break;
        case 'json':
            header('Content-Type: application/json');
            break;
        default:
            header('Content-Type: text/plain');
    }
    
    // Add cache control for development
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($filePath);
} else {
    http_response_code(404);
    echo 'File not found';
}
?>