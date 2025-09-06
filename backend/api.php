<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../database/database.php';

class API {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function handleRequest() {
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
        
        try {
            switch ($endpoint) {
                case 'users':
                    $this->handleUsers($method, $action, $id);
                    break;
                case 'balances':
                    $this->handleBalances($method, $action, $id);
                    break;
                case 'cards':
                    $this->handleCards($method, $action, $id);
                    break;
                case 'transactions':
                    $this->handleTransactions($method, $action, $id);
                    break;
                case 'notifications':
                    $this->handleNotifications($method, $action, $id);
                    break;
                case 'settings':
                    $this->handleSettings($method, $action, $id);
                    break;
                case 'auth':
                    $this->handleAuth($method, $action);
                    break;
                case 'admin':
                    $this->handleAdmin($method, $action, $id);
                    break;
                case 'crypto':
                    $this->handleCrypto($method, $action);
                    break;
                default:
                    $this->sendResponse(['error' => 'Invalid endpoint'], 404);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    private function handleAuth($method, $action) {
        session_start();
        
        if ($method === 'POST' && $action === 'login') {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                unset($user['password_hash']);
                $this->sendResponse(['success' => true, 'user' => $user, 'message' => 'Login successful']);
            } else {
                $this->sendResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
            }
        } elseif ($method === 'POST' && $action === 'register') {
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'] ?? '';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            if (!$name || !$email || !$password) {
                $this->sendResponse(['success' => false, 'message' => 'All fields are required'], 400);
                return;
            }
            
            if (strlen($password) < 8) {
                $this->sendResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
                return;
            }
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $this->db->getConnection()->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $passwordHash]);
                $userId = $this->db->getConnection()->lastInsertId();
                
                // Create default balances
                $this->createDefaultBalances($userId);
                
                // Auto-login after registration
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                
                $this->sendResponse(['success' => true, 'message' => 'Account created successfully', 'user_id' => $userId], 201);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                    $this->sendResponse(['success' => false, 'message' => 'Email already exists'], 409);
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Registration failed'], 500);
                }
            }
        } elseif ($method === 'POST' && $action === 'change-password') {
            if (!isset($_SESSION['user_id'])) {
                $this->sendResponse(['success' => false, 'message' => 'Not authenticated'], 401);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';
            
            if (!$currentPassword || !$newPassword) {
                $this->sendResponse(['success' => false, 'message' => 'Current and new passwords required'], 400);
                return;
            }
            
            if (strlen($newPassword) < 8) {
                $this->sendResponse(['success' => false, 'message' => 'New password must be at least 8 characters'], 400);
                return;
            }
            
            // Verify current password
            $stmt = $this->db->getConnection()->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $this->sendResponse(['success' => false, 'message' => 'Current password is incorrect'], 400);
                return;
            }
            
            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->getConnection()->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
            
            $this->sendResponse(['success' => true, 'message' => 'Password updated successfully']);
        }
    }
    
    private function handleUsers($method, $action, $id) {
        $pdo = $this->db->getConnection();
        
        switch ($method) {
            case 'GET':
                if ($id) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        unset($user['password_hash']);
                        $this->sendResponse($user);
                    } else {
                        $this->sendResponse(['error' => 'User not found'], 404);
                    }
                } else {
                    $stmt = $pdo->query("SELECT id, name, email, kyc_status, account_status, created_at FROM users ORDER BY created_at DESC");
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $this->sendResponse($users);
                }
                break;
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $name = $data['name'] ?? '';
                $email = $data['email'] ?? '';
                $password = $data['password'] ?? '';
                
                if (!$name || !$email || !$password) {
                    $this->sendResponse(['error' => 'Missing required fields'], 400);
                    return;
                }
                
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $email, $passwordHash]);
                    $userId = $pdo->lastInsertId();
                    
                    // Create default balances
                    $this->createDefaultBalances($userId);
                    
                    $this->sendResponse(['id' => $userId, 'message' => 'User created successfully'], 201);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                        $this->sendResponse(['error' => 'Email already exists'], 409);
                    } else {
                        throw $e;
                    }
                }
                break;
                
            case 'PUT':
                if (!$id) {
                    $this->sendResponse(['error' => 'User ID required'], 400);
                    return;
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                $updates = [];
                $params = [];
                
                if (isset($data['name'])) {
                    $updates[] = 'name = ?';
                    $params[] = $data['name'];
                }
                if (isset($data['kyc_status'])) {
                    $updates[] = 'kyc_status = ?';
                    $params[] = $data['kyc_status'];
                }
                if (isset($data['account_status'])) {
                    $updates[] = 'account_status = ?';
                    $params[] = $data['account_status'];
                }
                
                if (empty($updates)) {
                    $this->sendResponse(['error' => 'No fields to update'], 400);
                    return;
                }
                
                $params[] = $id;
                $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute($params);
                
                $this->sendResponse(['message' => 'User updated successfully']);
                break;
                
            case 'DELETE':
                if (!$id) {
                    $this->sendResponse(['error' => 'User ID required'], 400);
                    return;
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                
                $this->sendResponse(['message' => 'User deleted successfully']);
                break;
        }
    }
    
    private function createDefaultBalances($userId) {
        $currencies = ['BTC', 'ETH', 'USDT', 'USDC'];
        $stmt = $this->db->getConnection()->prepare("INSERT INTO user_balances (user_id, currency, balance) VALUES (?, ?, 0)");
        
        foreach ($currencies as $currency) {
            $stmt->execute([$userId, $currency]);
        }
    }
    
    private function handleBalances($method, $action, $id) {
        $pdo = $this->db->getConnection();
        
        switch ($method) {
            case 'GET':
                if ($action) { // Get balances for specific user
                    $stmt = $pdo->prepare("SELECT * FROM user_balances WHERE user_id = ?");
                    $stmt->execute([$action]);
                    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $this->sendResponse($balances);
                } else {
                    // Get all balances with user info
                    $stmt = $pdo->query("
                        SELECT ub.*, u.name, u.email 
                        FROM user_balances ub 
                        JOIN users u ON ub.user_id = u.id 
                        ORDER BY u.name, ub.currency
                    ");
                    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $this->sendResponse($balances);
                }
                break;
                
            case 'PUT':
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = $data['user_id'] ?? '';
                $currency = $data['currency'] ?? '';
                $balance = $data['balance'] ?? 0;
                
                if (!$userId || !$currency) {
                    $this->sendResponse(['error' => 'Missing required fields'], 400);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE user_balances SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND currency = ?");
                $stmt->execute([$balance, $userId, $currency]);
                
                $this->sendResponse(['message' => 'Balance updated successfully']);
                break;
        }
    }
    
    private function handleCards($method, $action, $id) {
        $pdo = $this->db->getConnection();
        
        switch ($method) {
            case 'GET':
                if ($action === 'user' && $id) {
                    // Get cards for specific user
                    $stmt = $pdo->prepare("SELECT * FROM virtual_cards WHERE user_id = ?");
                    $stmt->execute([$id]);
                } else {
                    // Get all cards with user info
                    $stmt = $pdo->query("
                        SELECT vc.*, u.name, u.email 
                        FROM virtual_cards vc 
                        JOIN users u ON vc.user_id = u.id 
                        ORDER BY vc.created_at DESC
                    ");
                }
                $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->sendResponse($cards);
                break;
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = $data['user_id'] ?? '';
                $cardType = $data['card_type'] ?? '';
                
                if (!$userId || !$cardType) {
                    $this->sendResponse(['error' => 'Missing required fields'], 400);
                    return;
                }
                
                // Generate card details
                $cardNumber = $this->generateCardNumber();
                $cvv = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                $expiryMonth = rand(1, 12);
                $expiryYear = date('Y') + rand(3, 5);
                
                // Get user name for cardholder
                $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    INSERT INTO virtual_cards (user_id, card_type, card_number, cvv, expiry_month, expiry_year, cardholder_name) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $cardType, $cardNumber, $cvv, $expiryMonth, $expiryYear, strtoupper($user['name'])]);
                
                $cardId = $pdo->lastInsertId();
                $this->sendResponse(['id' => $cardId, 'message' => 'Card created successfully'], 201);
                break;
                
            case 'PUT':
                if (!$id) {
                    $this->sendResponse(['error' => 'Card ID required'], 400);
                    return;
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                $updates = [];
                $params = [];
                
                if (isset($data['status'])) {
                    $updates[] = 'status = ?';
                    $params[] = $data['status'];
                }
                if (isset($data['spending_limit'])) {
                    $updates[] = 'spending_limit = ?';
                    $params[] = $data['spending_limit'];
                }
                
                if (empty($updates)) {
                    $this->sendResponse(['error' => 'No fields to update'], 400);
                    return;
                }
                
                $params[] = $id;
                $stmt = $pdo->prepare("UPDATE virtual_cards SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute($params);
                
                $this->sendResponse(['message' => 'Card updated successfully']);
                break;
        }
    }
    
    private function generateCardNumber() {
        // Generate a demo card number starting with 4000 (Visa test)
        $prefix = '4000';
        $middle = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $lastDigit = rand(0, 9);
        return $prefix . $middle . $lastDigit;
    }
    
    private function handleTransactions($method, $action, $id) {
        $pdo = $this->db->getConnection();
        
        switch ($method) {
            case 'GET':
                if ($action === 'user' && $id) {
                    // Get transactions for specific user
                    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$id]);
                } else {
                    // Get all transactions with user info
                    $stmt = $pdo->query("
                        SELECT t.*, u.name, u.email 
                        FROM transactions t 
                        JOIN users u ON t.user_id = u.id 
                        ORDER BY t.created_at DESC 
                        LIMIT 1000
                    ");
                }
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->sendResponse($transactions);
                break;
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = $data['user_id'] ?? '';
                $type = $data['type'] ?? '';
                $amount = $data['amount'] ?? 0;
                $currency = $data['currency'] ?? '';
                $description = $data['description'] ?? '';
                
                if (!$userId || !$type || !$amount || !$currency) {
                    $this->sendResponse(['error' => 'Missing required fields'], 400);
                    return;
                }
                
                $referenceId = 'TXN' . time() . rand(1000, 9999);
                
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, type, amount, currency, description, reference_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'completed')
                ");
                $stmt->execute([$userId, $type, $amount, $currency, $description, $referenceId]);
                
                // Update balance if it's a deposit or withdrawal
                if ($type === 'deposit') {
                    $this->updateBalance($userId, $currency, $amount);
                } elseif ($type === 'withdrawal') {
                    $this->updateBalance($userId, $currency, -$amount);
                }
                
                $txnId = $pdo->lastInsertId();
                $this->sendResponse(['id' => $txnId, 'reference_id' => $referenceId, 'message' => 'Transaction created successfully'], 201);
                break;
        }
    }
    
    private function updateBalance($userId, $currency, $amount) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE user_balances 
            SET balance = balance + ?, updated_at = CURRENT_TIMESTAMP 
            WHERE user_id = ? AND currency = ?
        ");
        $stmt->execute([$amount, $userId, $currency]);
    }
    
    private function handleNotifications($method, $action, $id) {
        $pdo = $this->db->getConnection();
        
        switch ($method) {
            case 'GET':
                if ($action === 'user' && $id) {
                    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$id]);
                } else {
                    $stmt = $pdo->query("
                        SELECT n.*, u.name, u.email 
                        FROM notifications n 
                        JOIN users u ON n.user_id = u.id 
                        ORDER BY n.created_at DESC
                    ");
                }
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->sendResponse($notifications);
                break;
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = $data['user_id'] ?? '';
                $title = $data['title'] ?? '';
                $message = $data['message'] ?? '';
                $type = $data['type'] ?? 'info';
                
                if (!$userId || !$title || !$message) {
                    $this->sendResponse(['error' => 'Missing required fields'], 400);
                    return;
                }
                
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $title, $message, $type]);
                
                $notificationId = $pdo->lastInsertId();
                $this->sendResponse(['id' => $notificationId, 'message' => 'Notification created successfully'], 201);
                break;
        }
    }
    
    private function handleSettings($method, $action, $id) {
        $pdo = $this->db->getConnection();
        
        switch ($method) {
            case 'GET':
                $stmt = $pdo->query("SELECT * FROM platform_settings");
                $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Convert to key-value pairs
                $settingsArray = [];
                foreach ($settings as $setting) {
                    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
                }
                
                $this->sendResponse($settingsArray);
                break;
                
            case 'PUT':
                $data = json_decode(file_get_contents('php://input'), true);
                
                foreach ($data as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) 
                        ON CONFLICT(setting_key) DO UPDATE SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $this->sendResponse(['message' => 'Settings updated successfully']);
                break;
        }
    }
    
    private function handleAdmin($method, $action, $id) {
        session_start();
        
        if ($method === 'POST' && $action === 'login') {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            // Demo admin credentials
            if ($email === 'admin@chainiq.com' && $password === 'admin123') {
                $_SESSION['admin_id'] = 1;
                $_SESSION['admin_email'] = $email;
                $this->sendResponse(['success' => true, 'message' => 'Admin login successful']);
            } else {
                $this->sendResponse(['success' => false, 'message' => 'Invalid admin credentials'], 401);
            }
        } elseif ($method === 'POST' && $action === 'set-password') {
            if (!isset($_SESSION['admin_id'])) {
                $this->sendResponse(['success' => false, 'message' => 'Admin access required'], 401);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? '';
            $newPassword = $data['new_password'] ?? '';
            
            if (!$userId || !$newPassword) {
                $this->sendResponse(['success' => false, 'message' => 'User ID and new password required'], 400);
                return;
            }
            
            if (strlen($newPassword) < 8) {
                $this->sendResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
                return;
            }
            
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->getConnection()->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);
            
            $this->sendResponse(['success' => true, 'message' => 'Password updated successfully']);
        }
    }
    
    private function handleCrypto($method, $action) {
        if ($method === 'GET' && $action === 'prices') {
            // Get live crypto prices from CoinGecko API
            $cryptoIds = 'bitcoin,ethereum,tether,usd-coin,binancecoin,cardano,solana,dogecoin';
            $url = "https://api.coingecko.com/api/v3/simple/price?ids={$cryptoIds}&vs_currencies=usd&include_24hr_change=true&include_market_cap=true";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Chain-IQ/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                // Fallback to demo data if API fails
                $demoData = [
                    'bitcoin' => ['usd' => 42500.00, 'usd_24h_change' => 2.5, 'usd_market_cap' => 835000000000],
                    'ethereum' => ['usd' => 2800.00, 'usd_24h_change' => -1.2, 'usd_market_cap' => 336000000000],
                    'tether' => ['usd' => 1.00, 'usd_24h_change' => 0.01, 'usd_market_cap' => 96000000000],
                    'usd-coin' => ['usd' => 1.00, 'usd_24h_change' => -0.01, 'usd_market_cap' => 33000000000]
                ];
                $this->sendResponse($demoData);
            } else {
                $data = json_decode($response, true);
                $this->sendResponse($data);
            }
        } elseif ($method === 'GET' && $action === 'addresses') {
            // Get deposit addresses for crypto assets
            $addresses = [
                'bitcoin' => [
                    'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
                    '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2',
                    '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy'
                ],
                'ethereum' => [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C66A8',
                    '0x123f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'
                ],
                'tether' => [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C66A8',
                    '0x456f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'
                ],
                'usd-coin' => [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C66A8',
                    '0x789f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'
                ]
            ];
            $this->sendResponse($addresses);
        } elseif ($method === 'PUT' && $action === 'addresses') {
            session_start();
            if (!isset($_SESSION['admin_id'])) {
                $this->sendResponse(['success' => false, 'message' => 'Admin access required'], 401);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            // In a real application, this would save to database
            // For demo purposes, we'll just confirm the update
            $this->sendResponse(['success' => true, 'message' => 'Addresses updated successfully']);
        }
    }
    
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}

// Initialize and handle request
$api = new API();
$api->handleRequest();
?>