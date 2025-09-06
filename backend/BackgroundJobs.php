<?php

class BackgroundJobProcessor {
    private $db;
    private $maxRunTime = 300; // 5 minutes
    private $batchSize = 10;
    
    public function __construct() {
        $this->db = MySQLDatabase::getInstance();
    }
    
    public function processJobs() {
        $startTime = time();
        $processedCount = 0;
        
        while ((time() - $startTime) < $this->maxRunTime) {
            $jobs = $this->getNextJobs();
            
            if (empty($jobs)) {
                break; // No more jobs to process
            }
            
            foreach ($jobs as $job) {
                try {
                    $this->processJob($job);
                    $processedCount++;
                } catch (Exception $e) {
                    $this->handleJobError($job, $e);
                }
            }
            
            // Small delay to prevent overwhelming the system
            usleep(100000); // 0.1 seconds
        }
        
        return $processedCount;
    }
    
    private function getNextJobs() {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE background_jobs 
            SET status = 'processing', updated_at = CURRENT_TIMESTAMP 
            WHERE status = 'pending' 
                AND scheduled_at <= CURRENT_TIMESTAMP 
                AND attempts < max_attempts 
            ORDER BY scheduled_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$this->batchSize]);
        
        if ($stmt->rowCount() === 0) {
            return [];
        }
        
        // Get the jobs we just marked as processing
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM background_jobs 
            WHERE status = 'processing' 
            ORDER BY updated_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$this->batchSize]);
        
        return $stmt->fetchAll();
    }
    
    private function processJob($job) {
        $this->markJobStarted($job['id']);
        
        $payload = json_decode($job['payload'], true);
        
        switch ($job['job_type']) {
            case 'csv_import':
                $this->processCsvImport($payload);
                break;
            case 'kyc_verification':
                $this->processKycVerification($payload);
                break;
            case 'email_notification':
                $this->processEmailNotification($payload);
                break;
            case 'balance_reconciliation':
                $this->processBalanceReconciliation($payload);
                break;
            case 'report_generation':
                $this->processReportGeneration($payload);
                break;
            default:
                throw new Exception("Unknown job type: {$job['job_type']}");
        }
        
        $this->markJobCompleted($job['id']);
    }
    
    private function processCsvImport($payload) {
        $filePath = $payload['file_path'];
        $importType = $payload['import_type'];
        
        if (!file_exists($filePath)) {
            throw new Exception("Import file not found: $filePath");
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open import file: $filePath");
        }
        
        $headers = fgetcsv($handle);
        $imported = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== false) {
            try {
                $row = array_combine($headers, $data);
                
                switch ($importType) {
                    case 'users':
                        $this->importUser($row);
                        break;
                    case 'transactions':
                        $this->importTransaction($row);
                        break;
                    default:
                        throw new Exception("Unknown import type: $importType");
                }
                
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        // Clean up file
        unlink($filePath);
        
        if (!empty($errors)) {
            throw new Exception("Import completed with errors. Imported: $imported. Errors: " . implode(', ', $errors));
        }
    }
    
    private function importUser($row) {
        $required = ['email', 'name'];
        foreach ($required as $field) {
            if (empty($row[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Generate random password if not provided
        $password = $row['password'] ?? bin2hex(random_bytes(8));
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO users (email, password_hash, name, verified_status) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $row['email'],
            $passwordHash,
            $row['name'],
            $row['verified_status'] ?? 0
        ]);
        
        $userId = $this->db->getConnection()->lastInsertId();
        
        // Create default balances
        $defaultAssets = ['BTC', 'ETH', 'USDT', 'USDC'];
        $balanceStmt = $this->db->getConnection()->prepare("
            INSERT INTO balances (user_id, asset, balance) VALUES (?, ?, ?)
        ");
        
        foreach ($defaultAssets as $asset) {
            $balanceStmt->execute([$userId, $asset, $row[$asset] ?? 0]);
        }
    }
    
    private function importTransaction($row) {
        $required = ['user_id', 'asset', 'amount', 'type'];
        foreach ($required as $field) {
            if (empty($row[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $transactionUuid = $row['transaction_uuid'] ?? $this->generateUUID();
        
        $this->db->executeWithTransaction(function($pdo) use ($row, $transactionUuid) {
            // Update balance
            $this->db->updateBalance(
                $row['user_id'],
                $row['asset'],
                $row['amount'],
                $row['type']
            );
            
            // Create transaction record
            $stmt = $pdo->prepare("
                INSERT INTO transactions (transaction_uuid, user_id, asset, amount, type, reference) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transactionUuid,
                $row['user_id'],
                $row['asset'],
                $row['amount'],
                $row['type'],
                $row['reference'] ?? null
            ]);
        });
    }
    
    private function processKycVerification($payload) {
        $submissionId = $payload['submission_id'];
        $action = $payload['action']; // 'approve' or 'reject'
        $reason = $payload['reason'] ?? '';
        
        $stmt = $this->db->getConnection()->prepare("
            UPDATE kyc_submissions 
            SET status = ?, decided_at = CURRENT_TIMESTAMP, reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$action === 'approve' ? 'approved' : 'rejected', $reason, $submissionId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("KYC submission not found: $submissionId");
        }
        
        // Send notification to user
        $this->sendKycNotification($submissionId, $action, $reason);
    }
    
    private function processEmailNotification($payload) {
        $to = $payload['to'];
        $subject = $payload['subject'];
        $message = $payload['message'];
        $headers = $payload['headers'] ?? 'From: noreply@chainiq.com';
        
        if (!mail($to, $subject, $message, $headers)) {
            throw new Exception("Failed to send email to: $to");
        }
    }
    
    private function processBalanceReconciliation($payload) {
        $userId = $payload['user_id'] ?? null;
        
        if ($userId) {
            $this->reconcileUserBalances($userId);
        } else {
            $this->reconcileAllBalances();
        }
    }
    
    private function reconcileUserBalances($userId) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT asset, 
                   SUM(CASE WHEN type IN ('credit', 'deposit') THEN amount ELSE 0 END) as total_credits,
                   SUM(CASE WHEN type IN ('debit', 'withdrawal') THEN amount ELSE 0 END) as total_debits
            FROM transactions 
            WHERE user_id = ? 
            GROUP BY asset
        ");
        $stmt->execute([$userId]);
        $calculations = $stmt->fetchAll();
        
        foreach ($calculations as $calc) {
            $calculatedBalance = $calc['total_credits'] - $calc['total_debits'];
            
            // Update balance if there's a discrepancy
            $stmt = $this->db->getConnection()->prepare("
                UPDATE balances 
                SET balance = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = ? AND asset = ? AND balance != ?
            ");
            $stmt->execute([$calculatedBalance, $userId, $calc['asset'], $calculatedBalance]);
        }
    }
    
    private function reconcileAllBalances() {
        $stmt = $this->db->getConnection()->prepare("SELECT DISTINCT user_id FROM balances");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            $this->reconcileUserBalances($user['user_id']);
        }
    }
    
    private function processReportGeneration($payload) {
        $reportType = $payload['report_type'];
        $filters = $payload['filters'] ?? [];
        $outputPath = $payload['output_path'];
        
        switch ($reportType) {
            case 'user_balances':
                $this->generateUserBalancesReport($filters, $outputPath);
                break;
            case 'transaction_summary':
                $this->generateTransactionSummaryReport($filters, $outputPath);
                break;
            default:
                throw new Exception("Unknown report type: $reportType");
        }
    }
    
    private function generateUserBalancesReport($filters, $outputPath) {
        $whereClause = '';
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $whereClause .= ' AND u.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['verified_only'])) {
            $whereClause .= ' AND u.verified_status = 1';
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.email, u.name, u.created_at, b.asset, b.balance
            FROM users u
            JOIN balances b ON u.id = b.user_id
            WHERE 1=1 $whereClause
            ORDER BY u.email, b.asset
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $this->generateCsvReport($data, $outputPath, ['Email', 'Name', 'Created', 'Asset', 'Balance']);
    }
    
    private function generateTransactionSummaryReport($filters, $outputPath) {
        $whereClause = '';
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $whereClause .= ' AND t.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= ' AND t.created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['asset'])) {
            $whereClause .= ' AND t.asset = ?';
            $params[] = $filters['asset'];
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT t.created_at, u.email, t.asset, t.amount, t.type, t.reference
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE 1=1 $whereClause
            ORDER BY t.created_at DESC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $this->generateCsvReport($data, $outputPath, ['Date', 'User Email', 'Asset', 'Amount', 'Type', 'Reference']);
    }
    
    private function generateCsvReport($data, $outputPath, $headers) {
        $handle = fopen($outputPath, 'w');
        if (!$handle) {
            throw new Exception("Cannot create report file: $outputPath");
        }
        
        fputcsv($handle, $headers);
        
        foreach ($data as $row) {
            fputcsv($handle, array_values($row));
        }
        
        fclose($handle);
    }
    
    private function sendKycNotification($submissionId, $action, $reason) {
        // Get user email
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.email FROM users u
            JOIN kyc_submissions k ON u.id = k.user_id
            WHERE k.id = ?
        ");
        $stmt->execute([$submissionId]);
        $email = $stmt->fetchColumn();
        
        if ($email) {
            $subject = "KYC Verification " . ucfirst($action);
            $message = "Your KYC verification has been $action.";
            if ($reason) {
                $message .= "\n\nReason: $reason";
            }
            
            $this->queueJob('email_notification', [
                'to' => $email,
                'subject' => $subject,
                'message' => $message
            ]);
        }
    }
    
    public function queueJob($jobType, $payload, $scheduledAt = null) {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO background_jobs (job_type, payload, scheduled_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $jobType,
            json_encode($payload),
            $scheduledAt ?? date('Y-m-d H:i:s')
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }
    
    private function markJobStarted($jobId) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE background_jobs 
            SET attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
    }
    
    private function markJobCompleted($jobId) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE background_jobs 
            SET status = 'completed', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
    }
    
    private function handleJobError($job, $exception) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE background_jobs 
            SET status = CASE 
                WHEN attempts >= max_attempts THEN 'failed' 
                ELSE 'pending' 
            END,
            error_message = ?,
            scheduled_at = CASE 
                WHEN attempts >= max_attempts THEN scheduled_at
                ELSE datetime(CURRENT_TIMESTAMP, '+' || (attempts * 5) || ' minutes')
            END,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        // MySQL version
        if (strpos($this->db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false) {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE background_jobs 
                SET status = CASE 
                    WHEN attempts >= max_attempts THEN 'failed' 
                    ELSE 'pending' 
                END,
                error_message = ?,
                scheduled_at = CASE 
                    WHEN attempts >= max_attempts THEN scheduled_at
                    ELSE DATE_ADD(CURRENT_TIMESTAMP, INTERVAL (attempts * 5) MINUTE)
                END,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
        }
        
        $stmt->execute([$exception->getMessage(), $job['id']]);
        
        error_log("Background job {$job['id']} failed: " . $exception->getMessage());
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