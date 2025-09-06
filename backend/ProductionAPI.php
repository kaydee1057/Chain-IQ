<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database/ProductionDatabase.php';
require_once __DIR__ . '/JWTManager.php';
require_once __DIR__ . '/SecurityManager.php';
require_once __DIR__ . '/AuditLogger.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// CORS Headers
$allowedOrigins = [
    'http://localhost:5000',
    'https://' . $_SERVER['HTTP_HOST'] ?? '',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID, Idempotency-Key');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

class ProductionAPI {
    private $db;
    private $jwtManager;
    private $securityManager;
    private $auditLogger;
    private $currentAdmin;
    private $requestId;
    
    public function __construct() {
        $this->db = ProductionDatabase::getInstance();
        $this->jwtManager = new JWTManager();
        $this->securityManager = new SecurityManager();
        $this->auditLogger = new AuditLogger($this->db);
        $this->requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? $this->generateRequestId();
        
        // Rate limiting
        $this->securityManager->checkRateLimit($_SERVER['REMOTE_ADDR']);
    }
    
    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $segments = explode('/', trim($path, '/'));
            
            // Remove 'api' from segments if present
            if ($segments[0] === 'api') {
                array_shift($segments);
            }
            
            $endpoint = $segments[0] ?? '';
            $action = $segments[1] ?? '';
            $id = $segments[2] ?? '';
            
            // Public endpoints (no auth required)
            if ($endpoint === 'auth' && $action === 'login') {
                $this->handleLogin();
                return;
            }
            
            if ($endpoint === 'auth' && $action === 'refresh') {
                $this->handleRefreshToken();
                return;
            }
            
            // All other endpoints require authentication
            $this->authenticateRequest();
            
            switch ($endpoint) {
                case 'auth':
                    $this->handleAuth($method, $action);
                    break;
                case 'users':
                    $this->handleUsers($method, $action, $id);
                    break;
                case 'balances':
                    $this->handleBalances($method, $action, $id);
                    break;
                case 'transactions':
                    $this->handleTransactions($method, $action, $id);
                    break;
                case 'cards':
                    $this->handleCards($method, $action, $id);
                    break;
                case 'kyc':
                    $this->handleKYC($method, $action, $id);
                    break;
                case 'notifications':
                    $this->handleNotifications($method, $action, $id);
                    break;
                case 'admin':
                    $this->handleAdmin($method, $action, $id);
                    break;
                case 'crypto':
                    $this->handleCrypto($method, $action);
                    break;
                case 'audit':
                    $this->handleAudit($method, $action);
                    break;
                default:
                    $this->sendError('Invalid endpoint', 404, 'ENDPOINT_NOT_FOUND');
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            $this->sendError('Internal server error', 500, 'INTERNAL_ERROR');
        }
    }
    
    private function authenticateRequest() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->sendError('Missing or invalid authorization header', 401, 'AUTH_REQUIRED');
        }
        
        $token = $matches[1];
        
        try {
            $payload = $this->jwtManager->validateToken($token);
            
            // Get admin user details
            $stmt = $this->db->getConnection()->prepare("SELECT * FROM admin_users WHERE id = ? AND status = 'active'");
            $stmt->execute([$payload['admin_id']]);
            $this->currentAdmin = $stmt->fetch();
            
            if (!$this->currentAdmin) {
                $this->sendError('Invalid token or admin not found', 401, 'AUTH_INVALID');
            }
            
        } catch (Exception $e) {
            $this->sendError('Invalid or expired token', 401, 'TOKEN_INVALID');
        }
    }
    
    private function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
        }
        
        $input = $this->getJsonInput();
        
        if (empty($input['email']) || empty($input['password'])) {
            $this->sendError('Email and password are required', 400, 'VALIDATION_ERROR');
        }
        
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM admin_users WHERE email = ? AND status = 'active'");
        $stmt->execute([$input['email']]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($input['password'], $admin['password_hash'])) {
            $this->auditLogger->log(null, 'login_failed', 'admin_user', $input['email']);
            $this->sendError('Invalid credentials', 401, 'AUTH_FAILED');
        }
        
        // Generate tokens
        $accessToken = $this->jwtManager->generateAccessToken($admin['id']);
        $refreshToken = $this->jwtManager->generateRefreshToken($admin['id']);
        
        // Update last login
        $stmt = $this->db->getConnection()->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        $this->auditLogger->log($admin['id'], 'login_success', 'admin_user', $admin['id']);
        
        $this->sendResponse([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'admin' => [
                'id' => $admin['id'],
                'email' => $admin['email'],
                'name' => $admin['name'],
                'role' => $admin['role']
            ]
        ]);
    }
    
    private function handleRefreshToken() {
        $input = $this->getJsonInput();
        
        if (empty($input['refresh_token'])) {
            $this->sendError('Refresh token required', 400, 'VALIDATION_ERROR');
        }
        
        try {
            $payload = $this->jwtManager->validateRefreshToken($input['refresh_token']);
            $newAccessToken = $this->jwtManager->generateAccessToken($payload['admin_id']);
            
            $this->sendResponse(['access_token' => $newAccessToken]);
        } catch (Exception $e) {
            $this->sendError('Invalid refresh token', 401, 'TOKEN_INVALID');
        }
    }
    
    private function handleUsers($method, $action, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getUser($id);
                } else {
                    $this->getUsers();
                }
                break;
            case 'POST':
                $this->createUser();
                break;
            case 'PUT':
                $this->updateUser($id);
                break;
            case 'DELETE':
                $this->deleteUser($id);
                break;
            default:
                $this->sendError('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
        }
    }
    
    private function getUsers() {
        $page = (int)($_GET['page'] ?? 1);
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        
        $whereClause = '';
        $params = [];
        
        if ($search) {
            $whereClause = 'WHERE email LIKE ? OR name LIKE ?';
            $params = ["%$search%", "%$search%"];
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT id, email, name, verified_status, created_at, updated_at 
            FROM users 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([...$params, $limit, $offset]);
        $users = $stmt->fetchAll();
        
        // Get total count
        $countStmt = $this->db->getConnection()->prepare("SELECT COUNT(*) FROM users $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        $this->auditLogger->log($this->currentAdmin['id'], 'users_list', 'users', null);
        
        $this->sendResponse([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    private function getUser($id) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.*, 
                   GROUP_CONCAT(b.asset || ':' || b.balance) as balances
            FROM users u
            LEFT JOIN balances b ON u.id = b.user_id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $this->sendError('User not found', 404, 'USER_NOT_FOUND');
        }
        
        // Parse balances
        $balances = [];
        if ($user['balances']) {
            foreach (explode(',', $user['balances']) as $balance) {
                [$asset, $amount] = explode(':', $balance);
                $balances[$asset] = (float)$amount;
            }
        }
        $user['balances'] = $balances;
        
        $this->auditLogger->log($this->currentAdmin['id'], 'user_view', 'user', $id);
        
        $this->sendResponse(['user' => $user]);
    }
    
    private function handleTransactions($method, $action, $id) {
        switch ($method) {
            case 'GET':
                if ($action === 'user' && $id) {
                    $this->getUserTransactions($id);
                } else {
                    $this->getAllTransactions();
                }
                break;
            case 'POST':
                $this->createTransaction();
                break;
            default:
                $this->sendError('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
        }
    }
    
    private function createTransaction() {
        $input = $this->getJsonInput();
        $this->validateInput($input, ['user_id', 'asset', 'amount', 'type']);
        
        $idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
        
        if ($idempotencyKey) {
            // Check if transaction already exists
            $stmt = $this->db->getConnection()->prepare("SELECT * FROM transactions WHERE transaction_uuid = ?");
            $stmt->execute([$idempotencyKey]);
            if ($existingTx = $stmt->fetch()) {
                $this->sendResponse(['transaction' => $existingTx]);
                return;
            }
        }
        
        $transactionUuid = $idempotencyKey ?? $this->generateUUID();
        
        try {
            $result = $this->db->executeWithTransaction(function($pdo) use ($input, $transactionUuid) {
                // Update balance
                $newBalance = $this->db->updateBalance(
                    $input['user_id'], 
                    $input['asset'], 
                    $input['amount'], 
                    $input['type']
                );
                
                // Create transaction record
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (transaction_uuid, user_id, admin_id, asset, amount, type, reference, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                ");
                $stmt->execute([
                    $transactionUuid,
                    $input['user_id'],
                    $this->currentAdmin['id'],
                    $input['asset'],
                    $input['amount'],
                    $input['type'],
                    $input['reference'] ?? null
                ]);
                
                return [
                    'id' => $pdo->lastInsertId(),
                    'transaction_uuid' => $transactionUuid,
                    'new_balance' => $newBalance
                ];
            });
            
            $this->auditLogger->log(
                $this->currentAdmin['id'], 
                'transaction_created', 
                'transaction', 
                $result['id'],
                ['amount' => $input['amount'], 'asset' => $input['asset'], 'type' => $input['type']]
            );
            
            $this->sendResponse(['transaction' => $result], 201);
            
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 422, 'TRANSACTION_FAILED');
        }
    }
    
    // Helper methods
    private function getJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('Invalid JSON', 400, 'INVALID_JSON');
        }
        return $input;
    }
    
    private function validateInput($input, $required) {
        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                $this->sendError("Field '$field' is required", 400, 'VALIDATION_ERROR');
            }
        }
    }
    
    private function sendResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
    
    private function sendError($message, $status = 400, $code = 'ERROR') {
        http_response_code($status);
        echo json_encode([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $this->requestId
            ]
        ]);
        exit;
    }
    
    private function generateRequestId() {
        return bin2hex(random_bytes(16));
    }
    
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Initialize and handle request
$api = new ProductionAPI();
$api->handleRequest();