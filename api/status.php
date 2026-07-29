<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');
$accounts = sodium_accessible_mail_accounts();
$forceRefresh = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['refresh'] ?? '') === '1';
$result = [];
$total = 0;
foreach ($accounts as $account) {
    if (!empty($account['password_cipher'])) {
        $account = sodium_refresh_account_cache((int) $account['id'], $forceRefresh);
        if($forceRefresh)sodium_process_auto_replies($account);
    }
    $unread = (int) ($account['unread_count'] ?? 0);
    $total += $unread;
    $result[] = [
        'id' => (int) $account['id'],
        'name' => (string) ($account['display_name'] ?: $account['email_address']),
        'email' => (string) $account['email_address'],
        'label' => (string) ($account['label_text'] ?? ''),
        'color' => (string) ($account['label_color'] ?? '#dc3545'),
        'unread' => $unread,
    ];
}
echo json_encode(['unified_unread' => $total, 'accounts' => $result, 'checked_at' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE);
