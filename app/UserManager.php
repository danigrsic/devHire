<?php
declare(strict_types=1);

namespace DevHire;

class UserManager
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Register a new user
     */
    public function registerUser(array $data): array
    {
        // Server-side validation
        $errors = [];

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $userType = ($data['user_type'] ?? 'user') === 'company' ? 'company' : 'user';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First and last name are required.';
        }

        // Check unique email
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email address already registered.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $activationToken = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, first_name, last_name, phone, user_type, is_active, activation_token) 
             VALUES (?, ?, ?, ?, ?, ?, FALSE, ?)'
        );
        $stmt->execute([$email, $passwordHash, $firstName, $lastName, $phone, $userType, $activationToken]);

        $userId = (int)$this->pdo->lastInsertId();

        // If company, create companies entry with placeholder name
        if ($userType === 'company') {
            $companyName = trim($data['company_name'] ?? $firstName . ' ' . $lastName);
            $stmt = $this->pdo->prepare('INSERT INTO companies (user_id, company_name) VALUES (?, ?)');
            $stmt->execute([$userId, $companyName]);
        }

        // Send activation email
        $mailer = new Mailer();
        $verifyLink = $this->getBaseUrl() . 'public/verify.php?token=' . $activationToken;
        $mailSent = $mailer->sendActivationEmail($email, $firstName, $verifyLink);

        return [
            'success' => true, 
            'user_id' => $userId,
            'mail_sent' => $mailSent,
            'verify_link' => $verifyLink // for dev fallback
        ];
    }

    public function activateUser(string $token): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = TRUE, activation_token = NULL WHERE activation_token = ?');
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    public function authenticate(string $email, string $password): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (!$user['is_active']) {
            return ['error' => 'inactive', 'message' => 'Please activate your account via email.'];
        }
        if (!$user['is_approved']) {
            return ['error' => 'not_approved', 'message' => 'Your account is pending admin approval.'];
        }

        return $user;
    }

    private function getBaseUrl(): string
    {
        if (function_exists('app_url')) {
            return app_url('');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/');
        return $protocol . '://' . $host . $scriptDir . '/';
    }
}
