<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../app/Database.php';
use DevHire\Database;

$email = trim($_GET['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false]);
    exit;
}
$pdo = Database::getConnection();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
$stmt->execute([$email]);
$exists = $stmt->fetchColumn() > 0;
echo json_encode(['exists' => $exists]);
