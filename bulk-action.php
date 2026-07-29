<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/index.php');
$selected = $_POST['messages'] ?? [];
$action = (string) ($_POST['bulk_action'] ?? '');
if (!in_array($action, ['archive', 'trash', 'junk', 'move', 'read', 'unread', 'tag'], true) || !$selected) {
    flash('warning', 'Sélectionnez au moins un message et une action.');
    redirect('/index.php');
}

$accounts = [];
foreach (sodium_accessible_mail_accounts() as $account) $accounts[(int) $account['id']] = $account;
$groups = [];
foreach ($selected as $encoded) {
    $decoded = json_decode((string) base64_decode((string) $encoded, true), true);
    $accountId = (int) ($decoded['account'] ?? 0);
    $folder = (string) ($decoded['folder'] ?? 'INBOX');
    $uid = (int) ($decoded['uid'] ?? 0);
    $key=(string)($decoded['key']??'');
    if ($uid && isset($accounts[$accountId])) $groups[$accountId][$folder][]=['uid'=>$uid,'key'=>$key];
}

try {
    foreach ($groups as $accountId => $folders) {
        $account = $accounts[$accountId];
        $target = $action === 'move'
            ? trim((string) ($_POST['target_folder'] ?? ''))
            : sodium_system_folder($account, $action);
        if ($action === 'move' && str_starts_with($target, '__')) $target = sodium_system_folder($account, substr($target, 2));
        if (in_array($action,['read','unread'],true)) {
            foreach($folders as $source=>$items)sodium_set_seen($account,$source,array_column($items,'uid'),$action==='read');
        } elseif($action==='tag') {
            $tagId=(int)($_POST['tag_id']??0);
            $tagVisibility=sodium_can_manage_all('sodium_labels')?'1=1':'(created_by=? OR is_shared=1)';
            $tagStmt=$pdo->prepare('SELECT COUNT(*) FROM sodium_tags WHERE id=? AND mail_account_id=? AND '.$tagVisibility);
            $tagParams=[$tagId,$accountId];if(!sodium_can_manage_all('sodium_labels'))$tagParams[]=(int)current_user()['id'];
            $tagStmt->execute($tagParams);
            if(!(int)$tagStmt->fetchColumn())continue;
            foreach($folders as $items)foreach($items as $item)if(preg_match('/^[a-f0-9]{64}$/',$item['key']))$pdo->prepare('INSERT IGNORE INTO sodium_message_tags (mail_account_id,message_key,tag_id,tagged_by) VALUES (?,?,?,?)')->execute([$accountId,$item['key'],$tagId,(int)current_user()['id']]);
        } else {
            if ($target === '' || strlen($target) > 250 || preg_match('/[\x00-\x1F]/', $target)) throw new RuntimeException('Dossier de destination invalide.');
            foreach ($folders as $source => $items) sodium_bulk_move($account, $source, array_column($items,'uid'), $target);
        }
        sodium_refresh_account_cache((int) $accountId, true);
    }
    flash('success', count($selected) . ' message(s) traité(s).');
} catch (Throwable $exception) {
    flash('danger', 'Action impossible : ' . $exception->getMessage());
}
$returnTo = (string) ($_POST['return_to'] ?? '/index.php');
redirect(str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/index.php');
