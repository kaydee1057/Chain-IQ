<?php

class AuditLogger {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function log($adminId, $action, $targetType = null, $targetId = null, $meta = null) {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? $this->generateRequestId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $metaJson = $meta ? json_encode($meta) : null;
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO audit_logs (admin_id, action, target_type, target_id, meta, ip, user_agent, request_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $adminId,
                $action,
                $targetType,
                $targetId,
                $metaJson,
                $ip,
                $userAgent,
                $requestId
            ]);
            
            return $this->db->getConnection()->lastInsertId();
        } catch (Exception $e) {
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAuditLogs($filters = [], $page = 1, $limit = 50) {
        $whereClause = '';
        $params = [];
        
        if (!empty($filters['admin_id'])) {
            $whereClause .= " AND admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        if (!empty($filters['action'])) {
            $whereClause .= " AND action LIKE ?";
            $params[] = "%{$filters['action']}%";
        }
        
        if (!empty($filters['target_type'])) {
            $whereClause .= " AND target_type = ?";
            $params[] = $filters['target_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT al.*, au.name as admin_name, au.email as admin_email
            FROM audit_logs al
            LEFT JOIN admin_users au ON al.admin_id = au.id
            WHERE 1=1 $whereClause
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([...$params, $limit, $offset]);
        $logs = $stmt->fetchAll();
        
        // Get total count
        $countStmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) FROM audit_logs WHERE 1=1 $whereClause
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        return [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    public function getAdminActivity($adminId, $days = 30) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as action_count,
                COUNT(DISTINCT action) as unique_actions
            FROM audit_logs 
            WHERE admin_id = ? AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        
        if (strpos($this->db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false) {
            // SQLite version
            $stmt = $this->db->getConnection()->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as action_count,
                    COUNT(DISTINCT action) as unique_actions
                FROM audit_logs 
                WHERE admin_id = ? AND created_at >= datetime('now', '-' || ? || ' days')
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
        }
        
        $stmt->execute([$adminId, $days]);
        return $stmt->fetchAll();
    }
    
    public function getSystemStats() {
        $stats = [];
        
        // Total actions today
        $stmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()
        ");
        
        if (strpos($this->db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false) {
            $stmt = $this->db->getConnection()->prepare("
                SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = DATE('now')
            ");
        }
        
        $stmt->execute();
        $stats['actions_today'] = $stmt->fetchColumn();
        
        // Most active admin today
        $stmt = $this->db->getConnection()->prepare("
            SELECT al.admin_id, au.name, COUNT(*) as actions
            FROM audit_logs al
            LEFT JOIN admin_users au ON al.admin_id = au.id
            WHERE DATE(al.created_at) = CURDATE()
            GROUP BY al.admin_id
            ORDER BY actions DESC
            LIMIT 1
        ");
        
        if (strpos($this->db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false) {
            $stmt = $this->db->getConnection()->prepare("
                SELECT al.admin_id, au.name, COUNT(*) as actions
                FROM audit_logs al
                LEFT JOIN admin_users au ON al.admin_id = au.id
                WHERE DATE(al.created_at) = DATE('now')
                GROUP BY al.admin_id
                ORDER BY actions DESC
                LIMIT 1
            ");
        }
        
        $stmt->execute();
        $stats['most_active_admin'] = $stmt->fetch();
        
        // Top actions today
        $stmt = $this->db->getConnection()->prepare("
            SELECT action, COUNT(*) as count
            FROM audit_logs 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY action
            ORDER BY count DESC
            LIMIT 5
        ");
        
        if (strpos($this->db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false) {
            $stmt = $this->db->getConnection()->prepare("
                SELECT action, COUNT(*) as count
                FROM audit_logs 
                WHERE DATE(created_at) = DATE('now')
                GROUP BY action
                ORDER BY count DESC
                LIMIT 5
            ");
        }
        
        $stmt->execute();
        $stats['top_actions'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    private function generateRequestId() {
        return bin2hex(random_bytes(16));
    }
}