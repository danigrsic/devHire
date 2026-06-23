<?php
declare(strict_types=1);

namespace DevHire;

class Messaging
{
    private static function hasColumn(\PDO $pdo, string $table, string $col): bool
    {
        static $cache = [];
        $key = "$table.$col";
        if (isset($cache[$key])) return $cache[$key];
        try {
            $pdo->query("SELECT `$col` FROM `$table` LIMIT 1");
            return $cache[$key] = true;
        } catch (\Throwable $e) {
            return $cache[$key] = false;
        }
    }

    private static function hasTable(\PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        try {
            $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            return $cache[$table] = true;
        } catch (\Throwable $e) {
            return $cache[$table] = false;
        }
    }

    public static function sendMessage(int $jobId, int $userId, int $companyId, int $senderUserId, string $senderType, string $message, bool $isApplication = false): int
    {
        $pdo = Database::getConnection();
        
        // Validate FKs first to give a clear error instead of 1452
        $chk = $pdo->prepare('SELECT (SELECT COUNT(*) FROM jobs WHERE id=?) + (SELECT COUNT(*) FROM users WHERE id=?) + (SELECT COUNT(*) FROM companies WHERE id=?) as ok');
        $chk->execute([$jobId, $userId, $companyId]);
        if ((int)$chk->fetchColumn() !== 3) {
            throw new \RuntimeException("Cannot send message: job/user/company does not exist (job=$jobId, user=$userId, company=$companyId)");
        }

        $hasSender = self::hasColumn($pdo, 'messages', 'sender_id');
        if ($hasSender) {
            $hasRejected = self::hasColumn($pdo, 'messages', 'is_rejected');
            $cols = 'job_id, user_id, company_id, sender_id, sender_type, message, is_application' . ($hasRejected ? ', is_rejected' : '');
            $vals = '?,?,?,?,?,?,?' . ($hasRejected ? ',0' : '');
            $stmt = $pdo->prepare("INSERT INTO messages ($cols) VALUES ($vals)");
            $stmt->execute([$jobId, $userId, $companyId, $senderUserId, $senderType, $message, $isApplication ? 1 : 0]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO messages (job_id, user_id, company_id, message) VALUES (?,?,?,?)');
            $stmt->execute([$jobId, $userId, $companyId, $message]);
        }
        return (int)$pdo->lastInsertId();
    }

    public static function hasUserApplied(int $jobId, int $userId): bool
    {
        $pdo = Database::getConnection();
        if (self::hasColumn($pdo, 'messages', 'is_application')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE job_id = ? AND user_id = ? AND is_application = 1');
            $stmt->execute([$jobId, $userId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE job_id = ? AND user_id = ?');
            $stmt->execute([$jobId, $userId]);
        }
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public static function getUnreadCountUser(int $userId): int
    {
        $pdo = Database::getConnection();
        if (!self::hasColumn($pdo, 'messages', 'sender_type')) return 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND sender_type = 'company' AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function getUnreadCountCompany(int $companyId): int
    {
        $pdo = Database::getConnection();
        if (!self::hasColumn($pdo, 'messages', 'sender_type')) return 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE company_id = ? AND sender_type = 'user' AND is_read = 0");
        $stmt->execute([$companyId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markThreadReadUser(int $jobId, int $userId, int $companyId): void
    {
        $pdo = Database::getConnection();
        if (!self::hasColumn($pdo, 'messages', 'sender_type')) return;
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE job_id = ? AND user_id = ? AND company_id = ? AND sender_type = 'company' AND is_read = 0");
        $stmt->execute([$jobId, $userId, $companyId]);
    }

    public static function markThreadReadCompany(int $jobId, int $userId, int $companyId): void
    {
        $pdo = Database::getConnection();
        if (!self::hasColumn($pdo, 'messages', 'sender_type')) return;
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE job_id = ? AND user_id = ? AND company_id = ? AND sender_type = 'user' AND is_read = 0");
        $stmt->execute([$jobId, $userId, $companyId]);
    }

    public static function threadUnreadCountUser(int $jobId, int $userId, int $companyId): int
    {
        $pdo = Database::getConnection();
        if (!self::hasColumn($pdo, 'messages', 'sender_type')) return 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE job_id=? AND user_id=? AND company_id=? AND sender_type='company' AND is_read=0");
        $stmt->execute([$jobId, $userId, $companyId]);
        return (int)$stmt->fetchColumn();
    }

    public static function threadUnreadCountCompany(int $jobId, int $userId, int $companyId): int
    {
        $pdo = Database::getConnection();
        if (!self::hasColumn($pdo, 'messages', 'sender_type')) return 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE job_id=? AND user_id=? AND company_id=? AND sender_type='user' AND is_read=0");
        $stmt->execute([$jobId, $userId, $companyId]);
        return (int)$stmt->fetchColumn();
    }

    /** Get application status for a user/job */
    public static function getApplicationStatus(int $jobId, int $userId, int $companyId): string
    {
        $pdo = Database::getConnection();

        // 1. Hired?
        if (self::hasTable($pdo, 'hires')) {
            $stmt = $pdo->prepare('SELECT 1 FROM hires WHERE job_id = ? AND user_id = ?');
            $stmt->execute([$jobId, $userId]);
            if ($stmt->fetchColumn()) return 'hired';
        }

        // 2. Rejected?
        if (self::hasColumn($pdo, 'messages', 'is_rejected') && self::hasColumn($pdo, 'messages', 'sender_type')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE job_id = ? AND user_id = ? AND company_id = ? AND sender_type = "company" AND is_rejected = 1');
            $stmt->execute([$jobId, $userId, $companyId]);
            if ((int)$stmt->fetchColumn() > 0) return 'rejected';
        }

        // 3. Company replied? -> interview
        if (self::hasColumn($pdo, 'messages', 'sender_type')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE job_id = ? AND user_id = ? AND company_id = ? AND sender_type = "company"');
            $stmt->execute([$jobId, $userId, $companyId]);
            if ((int)$stmt->fetchColumn() > 0) return 'interview';
        }

        // 4. default pending
        return 'pending';
    }

    public static function dismissApplication(int $userId, int $jobId): void
    {
        $pdo = Database::getConnection();
        if (!self::hasTable($pdo, 'application_dismissals')) return;
        $stmt = $pdo->prepare('INSERT IGNORE INTO application_dismissals (user_id, job_id) VALUES (?,?)');
        $stmt->execute([$userId, $jobId]);
    }

    public static function isDismissed(int $userId, int $jobId): bool
    {
        $pdo = Database::getConnection();
        if (!self::hasTable($pdo, 'application_dismissals')) return false;
        $stmt = $pdo->prepare('SELECT 1 FROM application_dismissals WHERE user_id=? AND job_id=?');
        $stmt->execute([$userId, $jobId]);
        return (bool)$stmt->fetchColumn();
    }
}
