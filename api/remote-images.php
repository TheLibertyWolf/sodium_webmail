<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

$sender = strtolower(trim((string) ($_POST['sender_email'] ?? '')));
if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Expéditeur invalide.']);
    exit;
}

$stmt = $pdo->prepare('INSERT IGNORE INTO sodium_remote_image_senders (user_id,sender_email) VALUES (?,?)');
$stmt->execute([(int) current_user()['id'], $sender]);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
