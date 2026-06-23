<?php
declare(strict_types=1);

namespace DevHire;

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['email'] = $user['email'];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        self::startSession();
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['user_type'],
            'first_name' => $_SESSION['first_name'],
            'email' => $_SESSION['email']
        ];
    }

    public static function requireLogin(?string $role = null): void
    {
        self::startSession();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /devhire/public/login.php');
            exit;
        }
        if ($role && $_SESSION['user_type'] !== $role) {
            http_response_code(403);
            die('Forbidden - insufficient role');
        }
    }

    public static function getCompanyId(int $userId): ?int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
