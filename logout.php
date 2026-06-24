<?php
declare(strict_types=1);
require_once __DIR__ . '/app/Auth.php';
use DevHire\Auth;
Auth::logout();
header('Location: /index.php');
exit;
