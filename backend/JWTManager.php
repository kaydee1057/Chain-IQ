<?php

class JWTManager {
    private $secretKey;
    private $algorithm = 'HS256';
    private $accessTokenTTL = 900; // 15 minutes
    private $refreshTokenTTL = 604800; // 7 days
    
    public function __construct() {
        $this->secretKey = $_ENV['JWT_SECRET'] ?? 'your-super-secret-key-change-in-production';
    }
    
    public function generateAccessToken($adminId) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode([
            'admin_id' => $adminId,
            'iat' => time(),
            'exp' => time() + $this->accessTokenTTL,
            'type' => 'access'
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secretKey, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    public function generateRefreshToken($adminId) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->refreshTokenTTL);
        
        // Store in database
        $db = MySQLDatabase::getInstance();
        $stmt = $db->getConnection()->prepare("
            INSERT INTO refresh_tokens (admin_id, token_hash, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$adminId, $tokenHash, $expiresAt]);
        
        return $token;
    }
    
    public function validateToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        [$base64Header, $base64Payload, $base64Signature] = $parts;
        
        // Verify signature
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secretKey, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($expectedSignature, $base64Signature)) {
            throw new Exception('Invalid token signature');
        }
        
        // Decode payload
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
        
        if (!$payload) {
            throw new Exception('Invalid token payload');
        }
        
        // Check expiration
        if ($payload['exp'] < time()) {
            throw new Exception('Token expired');
        }
        
        return $payload;
    }
    
    public function validateRefreshToken($token) {
        $tokenHash = hash('sha256', $token);
        
        $db = MySQLDatabase::getInstance();
        $stmt = $db->getConnection()->prepare("
            SELECT * FROM refresh_tokens 
            WHERE token_hash = ? AND expires_at > CURRENT_TIMESTAMP AND revoked = 0
        ");
        $stmt->execute([$tokenHash]);
        $tokenRecord = $stmt->fetch();
        
        if (!$tokenRecord) {
            throw new Exception('Invalid or expired refresh token');
        }
        
        return ['admin_id' => $tokenRecord['admin_id']];
    }
    
    public function revokeRefreshToken($token) {
        $tokenHash = hash('sha256', $token);
        
        $db = MySQLDatabase::getInstance();
        $stmt = $db->getConnection()->prepare("
            UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = ?
        ");
        $stmt->execute([$tokenHash]);
    }
}