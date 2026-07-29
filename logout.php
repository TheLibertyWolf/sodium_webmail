<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') redirect('/login.php');

if (current_user()) {
    clear_remember_cookie((int) $_SESSION['user_id']);
}
$_SESSION = [];
session_destroy();
redirect('/login.php');
