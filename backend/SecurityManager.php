<?php

class SecurityManager {
    private $rateLimits = [];
    private $maxRequests = 60; // per minute
    private $maxTransactionRequests = 10; // per minute for transaction endpoints
    
    public function checkRateLimit($clientIP, $endpoint = null) {
        $key = $clientIP . ($endpoint ? ":$endpoint" : '');
        $now = time();
        $windowStart = $now - 60; // 1 minute window
        
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [];
        }
        
        // Remove old requests outside the window
        $this->rateLimits[$key] = array_filter($this->rateLimits[$key], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        $limit = $this->isTransactionEndpoint($endpoint) ? $this->maxTransactionRequests : $this->maxRequests;
        
        if (count($this->rateLimits[$key]) >= $limit) {
            http_response_code(429);
            echo json_encode([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => 60
                ]
            ]);
            exit;
        }
        
        $this->rateLimits[$key][] = $now;
    }
    
    private function isTransactionEndpoint($endpoint) {
        $transactionEndpoints = ['transactions', 'balances'];
        return in_array($endpoint, $transactionEndpoints);
    }
    
    public function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                if (isset($rule['required']) && $rule['required']) {
                    $errors[$field] = "Field '$field' is required";
                }
                continue;
            }
            
            $value = $data[$field];
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "Invalid email format";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = "Must be a number";
                        }
                        break;
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field] = "Must be a string";
                        }
                        break;
                }
            }
            
            // Length validation
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = "Must be at least {$rule['min_length']} characters";
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = "Must be no more than {$rule['max_length']} characters";
            }
            
            // Custom validation
            if (isset($rule['custom']) && is_callable($rule['custom'])) {
                $customResult = $rule['custom']($value);
                if ($customResult !== true) {
                    $errors[$field] = $customResult;
                }
            }
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $errors
                ]
            ]);
            exit;
        }
        
        return true;
    }
    
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }
    
    public function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        error_log("SECURITY: " . json_encode($logEntry));
    }
}