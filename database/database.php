<?php
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:chainiq.db');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function createTables() {
        $queries = [
            // Users table
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                pin_hash VARCHAR(255),
                kyc_status TEXT DEFAULT 'pending' CHECK (kyc_status IN ('pending', 'verified', 'rejected')),
                account_status TEXT DEFAULT 'active' CHECK (account_status IN ('active', 'frozen', 'suspended')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // User balances table
            "CREATE TABLE IF NOT EXISTS user_balances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                currency VARCHAR(10) NOT NULL,
                balance DECIMAL(20, 8) DEFAULT 0,
                locked_balance DECIMAL(20, 8) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, currency)
            )",
            
            // Virtual cards table
            "CREATE TABLE IF NOT EXISTS virtual_cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                card_type TEXT NOT NULL CHECK (card_type IN ('platinum', 'gold', 'premium')),
                card_number VARCHAR(16) NOT NULL,
                cvv VARCHAR(3) NOT NULL,
                expiry_month INTEGER NOT NULL,
                expiry_year INTEGER NOT NULL,
                cardholder_name VARCHAR(100) NOT NULL,
                status TEXT DEFAULT 'active' CHECK (status IN ('active', 'frozen', 'expired')),
                spending_limit DECIMAL(15, 2) DEFAULT 10000.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Transactions table
            "CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK (type IN ('deposit', 'withdrawal', 'transfer', 'card_payment', 'convert')),
                amount DECIMAL(20, 8) NOT NULL,
                currency VARCHAR(10) NOT NULL,
                to_currency VARCHAR(10),
                status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed', 'cancelled')),
                description TEXT,
                reference_id VARCHAR(50),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Notifications table
            "CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                type TEXT DEFAULT 'info' CHECK (type IN ('info', 'warning', 'success', 'error')),
                is_read BOOLEAN DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Platform settings table
            "CREATE TABLE IF NOT EXISTS platform_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // Card requests table
            "CREATE TABLE IF NOT EXISTS card_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                card_type TEXT NOT NULL CHECK (card_type IN ('platinum', 'gold', 'premium')),
                status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
                requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        ];
        
        foreach ($queries as $query) {
            $this->pdo->exec($query);
        }
        
        // Insert default platform settings
        $this->insertDefaultSettings();
        
        // Create demo users
        $this->createDemoUsers();
    }
    
    private function insertDefaultSettings() {
        $defaultSettings = [
            ['app_name', 'Chain-IQ'],
            ['logo_initials', 'CIQ'],
            ['logo_color', '#ffd75a'],
            ['bg_color', '#0a0a0f'],
            ['font_color', '#f0f0f5'],
            ['btn_color', '#5a8cff'],
            ['card_color', '#121218'],
            ['card_brand_name', 'Chain-IQ'],
            ['enable_stacked_view', '1'],
            ['stacked_card_count', 'all'],
            ['lock_card_requests', '0'],
            ['lock_reason', 'kyc']
        ];
        
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
    }
    
    private function createDemoUsers() {
        // Check if demo users already exist
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email IN (?, ?)");
        $stmt->execute(['user@demo.com', 'admin@chainiq.com']);
        $existingCount = $stmt->fetchColumn();
        
        if ($existingCount > 0) {
            return; // Demo users already exist
        }
        
        // Create demo user
        $userPasswordHash = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, kyc_status, account_status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Demo User', 'user@demo.com', $userPasswordHash, 'verified', 'active']);
        $userId = $this->pdo->lastInsertId();
        
        // Create demo admin
        $adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt->execute(['Admin User', 'admin@chainiq.com', $adminPasswordHash, 'verified', 'active']);
        $adminId = $this->pdo->lastInsertId();
        
        // Create default balances for demo user
        $this->createDefaultBalancesForUser($userId);
        $this->createDefaultBalancesForUser($adminId);
        
        // Create demo virtual card for user
        $this->createDemoCard($userId);
    }
    
    private function createDefaultBalancesForUser($userId) {
        $currencies = ['BTC', 'ETH', 'USDT', 'USDC'];
        $defaultBalances = ['BTC' => 0.0345, 'ETH' => 0.752, 'USDT' => 1250.50, 'USDC' => 890.25];
        
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO user_balances (user_id, currency, balance) VALUES (?, ?, ?)");
        
        foreach ($currencies as $currency) {
            $balance = $defaultBalances[$currency] ?? 0;
            $stmt->execute([$userId, $currency, $balance]);
        }
    }
    
    private function createDemoCard($userId) {
        $cardNumber = '4532' . sprintf('%012d', mt_rand(0, 999999999999));
        $cvv = sprintf('%03d', mt_rand(100, 999));
        $expiryMonth = mt_rand(1, 12);
        $expiryYear = date('Y') + mt_rand(2, 5);
        
        // Get the user's name for the card
        $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userName = $stmt->fetchColumn() ?? 'Demo User';
        
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO virtual_cards (user_id, card_type, card_number, cvv, expiry_month, expiry_year, cardholder_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, 'platinum', $cardNumber, $cvv, $expiryMonth, $expiryYear, $userName, 'active']);
    }
}
?>