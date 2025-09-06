<?php

class MySQLDatabase {
    private $pdo;
    private static $instance = null;
    private $isProduction = false;
    
    private function __construct() {
        $this->isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
        
        try {
            if ($this->isProduction || isset($_ENV['DB_HOST'])) {
                // Production MySQL connection for Hostinger
                $dsn = "mysql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . 
                       ";port=" . ($_ENV['DB_PORT'] ?? '3306') . 
                       ";dbname=" . ($_ENV['DB_NAME'] ?? 'admin_app') . 
                       ";charset=utf8mb4";
                       
                $this->pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
            } else {
                // Development SQLite connection for Replit (MySQL-compatible syntax)
                $this->pdo = new PDO('sqlite:' . __DIR__ . '/../admin_app_mysql.db');
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                
                // SQLite optimizations
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
                $this->pdo->exec('PRAGMA synchronous = NORMAL');
            }
            
            $this->createSchema();
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function createSchema() {
        if ($this->isProduction) {
            $this->createMySQLSchema();
        } else {
            $this->createSQLiteSchema();
        }
        
        $this->createIndexes();
        $this->insertDefaultData();
    }
    
    private function createMySQLSchema() {
        // Production MySQL schema exactly as documented
        $queries = [
            "CREATE DATABASE IF NOT EXISTS admin_app CHARACTER SET = 'utf8mb4' COLLATE = 'utf8mb4_unicode_ci'",
            "USE admin_app",
            
            // USERS
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(200),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                verified_status TINYINT(1) DEFAULT 0,
                metadata JSON DEFAULT NULL
            )",
            
            // BALANCES
            "CREATE TABLE IF NOT EXISTS balances (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                asset VARCHAR(32) NOT NULL,
                balance DECIMAL(36,8) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_user_asset (user_id, asset),
                INDEX idx_user (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // TRANSACTIONS
            "CREATE TABLE IF NOT EXISTS transactions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                transaction_uuid CHAR(36) NOT NULL UNIQUE,
                user_id BIGINT UNSIGNED NOT NULL,
                admin_id BIGINT UNSIGNED NULL,
                asset VARCHAR(32) NOT NULL,
                amount DECIMAL(36,8) NOT NULL,
                type ENUM('credit','debit','deposit','withdrawal','conversion','fee','adjustment') NOT NULL,
                reference VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_time (user_id, created_at),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            // KYC SUBMISSIONS
            "CREATE TABLE IF NOT EXISTS kyc_submissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                status ENUM('pending','approved','rejected') DEFAULT 'pending',
                reason TEXT NULL,
                document_url VARCHAR(1024) NULL,
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                decided_at TIMESTAMP NULL DEFAULT NULL,
                decided_by BIGINT UNSIGNED NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            // CARDS
            "CREATE TABLE IF NOT EXISTS cards (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL,
                limit_amount DECIMAL(36,8) DEFAULT NULL,
                status ENUM('active','frozen','cancelled') DEFAULT 'active',
                issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            // DEPOSIT ADDRESSES POOL
            "CREATE TABLE IF NOT EXISTS deposit_addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset VARCHAR(32) NOT NULL,
                address VARCHAR(255) NOT NULL,
                assigned_to BIGINT UNSIGNED NULL,
                assigned_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX ux_asset_address (asset, address),
                FOREIGN KEY (assigned_to) REFERENCES users(id)
            )",
            
            // NOTIFICATIONS
            "CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_read (user_id, is_read)
            )",
            
            // FEES
            "CREATE TABLE IF NOT EXISTS fees (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                fee_type VARCHAR(100) NOT NULL,
                value DECIMAL(18,8) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // AUDIT LOGS
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id BIGINT UNSIGNED NULL,
                action VARCHAR(255) NOT NULL,
                target_type VARCHAR(100) NULL,
                target_id VARCHAR(255) NULL,
                meta JSON NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(1024) NULL,
                request_id CHAR(36) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            // CRYPTO PRICES
            "CREATE TABLE IF NOT EXISTS crypto_prices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset VARCHAR(64) NOT NULL UNIQUE,
                price_usd DECIMAL(30,8) NOT NULL,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // ADMIN USERS
            "CREATE TABLE IF NOT EXISTS admin_users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(200),
                role VARCHAR(50) DEFAULT 'admin',
                permissions JSON DEFAULT NULL,
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                status ENUM('active','suspended','inactive') DEFAULT 'active'
            )",
            
            // REFRESH TOKENS
            "CREATE TABLE IF NOT EXISTS refresh_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id BIGINT UNSIGNED NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                revoked TINYINT(1) DEFAULT 0,
                FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
            )",
            
            // BACKGROUND JOBS
            "CREATE TABLE IF NOT EXISTS background_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_type VARCHAR(100) NOT NULL,
                payload JSON NOT NULL,
                status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($queries as $query) {
            $this->pdo->exec($query);
        }
    }
    
    private function createSQLiteSchema() {
        // SQLite schema for development (MySQL-compatible where possible)
        $queries = [
            // USERS
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(200),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                verified_status INTEGER DEFAULT 0,
                metadata TEXT DEFAULT NULL
            )",
            
            // BALANCES
            "CREATE TABLE IF NOT EXISTS balances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                asset VARCHAR(32) NOT NULL,
                balance DECIMAL(36,8) NOT NULL DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, asset)
            )",
            
            // TRANSACTIONS
            "CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_uuid CHAR(36) NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                admin_id INTEGER NULL,
                asset VARCHAR(32) NOT NULL,
                amount DECIMAL(36,8) NOT NULL,
                type VARCHAR(50) NOT NULL CHECK (type IN ('credit','debit','deposit','withdrawal','conversion','fee','adjustment')),
                reference VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            // KYC SUBMISSIONS
            "CREATE TABLE IF NOT EXISTS kyc_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
                reason TEXT NULL,
                document_url VARCHAR(1024) NULL,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                decided_at DATETIME NULL DEFAULT NULL,
                decided_by INTEGER NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            // CARDS
            "CREATE TABLE IF NOT EXISTS cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type VARCHAR(64) NOT NULL,
                limit_amount DECIMAL(36,8) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','frozen','cancelled')),
                issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            // DEPOSIT ADDRESSES
            "CREATE TABLE IF NOT EXISTS deposit_addresses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset VARCHAR(32) NOT NULL,
                address VARCHAR(255) NOT NULL,
                assigned_to INTEGER NULL,
                assigned_at DATETIME NULL DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (assigned_to) REFERENCES users(id)
            )",
            
            // NOTIFICATIONS
            "CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // FEES
            "CREATE TABLE IF NOT EXISTS fees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                fee_type VARCHAR(100) NOT NULL,
                value DECIMAL(18,8) NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // AUDIT LOGS
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER NULL,
                action VARCHAR(255) NOT NULL,
                target_type VARCHAR(100) NULL,
                target_id VARCHAR(255) NULL,
                meta TEXT NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(1024) NULL,
                request_id CHAR(36) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // CRYPTO PRICES
            "CREATE TABLE IF NOT EXISTS crypto_prices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset VARCHAR(64) NOT NULL UNIQUE,
                price_usd DECIMAL(30,8) NOT NULL,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // ADMIN USERS
            "CREATE TABLE IF NOT EXISTS admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(200),
                role VARCHAR(50) DEFAULT 'admin',
                permissions TEXT DEFAULT NULL,
                last_login DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','suspended','inactive'))
            )",
            
            // REFRESH TOKENS
            "CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                revoked INTEGER DEFAULT 0,
                FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
            )",
            
            // BACKGROUND JOBS
            "CREATE TABLE IF NOT EXISTS background_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_type VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','processing','completed','failed')),
                attempts INTEGER DEFAULT 0,
                max_attempts INTEGER DEFAULT 3,
                error_message TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($queries as $query) {
            $this->pdo->exec($query);
        }
    }
    
    private function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_balances_user ON balances(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_balances_asset ON balances(asset)",
            "CREATE INDEX IF NOT EXISTS idx_transactions_user_time ON transactions(user_id, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_transactions_uuid ON transactions(transaction_uuid)",
            "CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type)",
            "CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read)",
            "CREATE INDEX IF NOT EXISTS idx_audit_logs_admin ON audit_logs(admin_id)",
            "CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action)",
            "CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_deposit_addresses_asset ON deposit_addresses(asset)",
            "CREATE INDEX IF NOT EXISTS idx_deposit_addresses_assigned ON deposit_addresses(assigned_to)",
            "CREATE INDEX IF NOT EXISTS idx_cards_user ON cards(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_cards_status ON cards(status)",
            "CREATE INDEX IF NOT EXISTS idx_kyc_status ON kyc_submissions(status)",
            "CREATE INDEX IF NOT EXISTS idx_refresh_tokens_admin ON refresh_tokens(admin_id)",
            "CREATE INDEX IF NOT EXISTS idx_jobs_status ON background_jobs(status)",
            "CREATE INDEX IF NOT EXISTS idx_jobs_type ON background_jobs(job_type)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->pdo->exec($index);
            } catch (PDOException $e) {
                // Index might already exist, continue
            }
        }
    }
    
    private function insertDefaultData() {
        // Create default admin user
        $adminEmail = 'admin@chainiq.com';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE email = ?");
        $stmt->execute([$adminEmail]);
        
        if ($stmt->fetchColumn() == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_ARGON2ID);
            $stmt = $this->pdo->prepare("INSERT INTO admin_users (email, password_hash, name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$adminEmail, $adminPassword, 'System Administrator', 'super_admin']);
        }
        
        // Create demo user
        $userEmail = 'user@demo.com';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$userEmail]);
        
        if ($stmt->fetchColumn() == 0) {
            $userPassword = password_hash('password123', PASSWORD_ARGON2ID);
            $stmt = $this->pdo->prepare("INSERT INTO users (email, password_hash, name, verified_status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userEmail, $userPassword, 'Demo User', 1]);
            $userId = $this->pdo->lastInsertId();
            
            // Create default balances
            $this->createDefaultBalances($userId);
        }
        
        // Create deposit addresses pool
        $this->createDepositAddressPool();
    }
    
    private function createDefaultBalances($userId) {
        $defaultBalances = [
            ['BTC', '0.0345'],
            ['ETH', '0.752'],
            ['USDT', '1250.50'],
            ['USDC', '890.25']
        ];
        
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO balances (user_id, asset, balance) VALUES (?, ?, ?)");
        if ($this->isProduction) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO balances (user_id, asset, balance) VALUES (?, ?, ?)");
        }
        
        foreach ($defaultBalances as [$asset, $balance]) {
            $stmt->execute([$userId, $asset, $balance]);
        }
    }
    
    private function createDepositAddressPool() {
        $addresses = [
            ['BTC', '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'],
            ['BTC', '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy'],
            ['ETH', '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed'],
            ['ETH', '0xfB6916095ca1df60bB79Ce92cE3Ea74c37c5d359'],
            ['USDT', '0x4838B106FCe9647Bdf1E7877BF73cE8B0BAD5f97'],
            ['USDC', '0x8ba1f109551bD432803012645Hac136c22C501ad']
        ];
        
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO deposit_addresses (asset, address) VALUES (?, ?)");
        if ($this->isProduction) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO deposit_addresses (asset, address) VALUES (?, ?)");
        }
        
        foreach ($addresses as [$asset, $address]) {
            $stmt->execute([$asset, $address]);
        }
    }
    
    // Transaction handling with proper locking
    public function executeWithTransaction(callable $callback) {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // MySQL FOR UPDATE syntax (SQLite will ignore)
    public function getUserBalanceForUpdate($userId, $asset) {
        if ($this->isProduction) {
            $stmt = $this->pdo->prepare("SELECT * FROM balances WHERE user_id = ? AND asset = ? FOR UPDATE");
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM balances WHERE user_id = ? AND asset = ?");
        }
        $stmt->execute([$userId, $asset]);
        return $stmt->fetch();
    }
    
    // Assign deposit address with SKIP LOCKED (MySQL feature)
    public function assignDepositAddress($userId, $asset) {
        return $this->executeWithTransaction(function($pdo) use ($userId, $asset) {
            if ($this->isProduction) {
                $stmt = $pdo->prepare("
                    SELECT * FROM deposit_addresses 
                    WHERE asset = ? AND assigned_to IS NULL 
                    ORDER BY created_at ASC 
                    LIMIT 1 FOR UPDATE SKIP LOCKED
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM deposit_addresses 
                    WHERE asset = ? AND assigned_to IS NULL 
                    ORDER BY created_at ASC 
                    LIMIT 1
                ");
            }
            
            $stmt->execute([$asset]);
            $address = $stmt->fetch();
            
            if (!$address) {
                throw new Exception("No available deposit addresses for $asset");
            }
            
            $updateStmt = $pdo->prepare("
                UPDATE deposit_addresses 
                SET assigned_to = ?, assigned_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $updateStmt->execute([$userId, $address['id']]);
            
            return $address;
        });
    }
    
    // Update balance atomically
    public function updateBalance($userId, $asset, $amount, $type = 'credit') {
        return $this->executeWithTransaction(function($pdo) use ($userId, $asset, $amount, $type) {
            // Lock the balance record
            $balance = $this->getUserBalanceForUpdate($userId, $asset);
            
            if (!$balance) {
                // Create balance record if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO balances (user_id, asset, balance) VALUES (?, ?, ?)");
                $newBalance = ($type === 'credit') ? $amount : 0;
                $stmt->execute([$userId, $asset, $newBalance]);
                return $newBalance;
            }
            
            $currentBalance = $balance['balance'];
            $newBalance = ($type === 'credit') ? $currentBalance + $amount : $currentBalance - $amount;
            
            if ($newBalance < 0 && $type === 'debit') {
                throw new Exception("Insufficient balance");
            }
            
            $stmt = $pdo->prepare("UPDATE balances SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND asset = ?");
            $stmt->execute([$newBalance, $userId, $asset]);
            
            return $newBalance;
        });
    }
}