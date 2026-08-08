<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();

try {
    $indexed = 0;
    $requestedAccountId = (int)($_POST['account_id'] ?? 0);
    $accounts = sodium_accessible_mail_accounts();
    if ($requestedAccountId) {
        $accounts = array_values(array_filter($accounts, static fn(array $account): bool => (int)$account['id'] === $requestedAccountId));
        if (!$accounts) throw new RuntimeException('Compte mail inaccessible.');
    }
    $refreshedAccounts = 0;
    foreach ($accounts as $account) {
        if (!empty($account['password_cipher'])) {
            $refreshed = sodium_refresh_account_cache((int) $account['id'], true);
            $indexed += sodium_index_account_history($refreshed);
            sodium_process_auto_replies($refreshed);
            $refreshedAccounts++;
        }
    }
} catch (Throwable $exception) {
    flash('danger', 'Relève impossible : ' . $exception->getMessage());
}
$returnTo = (string) ($_POST['return_to'] ?? '/index.php');
redirect(str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/index.php');
