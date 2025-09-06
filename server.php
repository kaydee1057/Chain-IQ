<?php
/*
 * Chain-IQ Main Server
 * 
 * ROUTING STRUCTURE:
 * / -> Main user dashboard (Chain-IQ.html)
 * /admin -> Admin panel (Backoffice.html)
 * /register -> User registration page
 */

// Add cache control headers for Replit proxy environment
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();

// Get the request URI
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove query parameters and trailing slashes
$path = rtrim($path, '/');
if ($path === '') $path = '/';

// Route handling
switch ($path) {
    case '/':
    case '/dashboard':
        include 'frontend/Chain-IQ.html';
        break;
        
    case '/register':
        // Show registration page
        include 'frontend/register.html';
        break;
        
    case '/admin':
        include 'frontend/Backoffice.html';
        break;
        
    default:
        // Handle API routes
        if (strpos($path, '/api/') === 0) {
            include 'backend/CompleteAPI.php';
        }
        // Handle static files
        elseif (strpos($path, '/frontend/') === 0) {
            $file_path = '.' . $path;
            if (file_exists($file_path)) {
                $mime_type = mime_content_type($file_path);
                header('Content-Type: ' . $mime_type);
                readfile($file_path);
            } else {
                http_response_code(404);
                echo "File not found";
            }
        }
        else {
            http_response_code(404);
            echo "Page not found";
        }
        break;
}
?>