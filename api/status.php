<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');
$accounts = sodium_accessible_mail_accounts();
$forceRefresh = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['refresh'] ?? '') === '1';
$result = [];
$total = 0;
$newMessages=0;
$newMessageDetails=[];$newMessageAccounts=[];
foreach ($accounts as $account) {
    $before=(int)($account['unread_count']??0);
    if (!empty($account['password_cipher'])) {
        $account = sodium_refresh_account_cache((int) $account['id'], $forceRefresh);
        if($forceRefresh)sodium_process_auto_replies($account);
    }
    $unread = (int) ($account['unread_count'] ?? 0);
    if($forceRefresh){$delta=max(0,$unread-$before);$newMessages+=$delta;if($delta>0){try{$newMessageDetails=array_merge($newMessageDetails,sodium_latest_unread_notification_messages($account,min(3,$delta)));}catch(Throwable){}$newMessageAccounts[]=(string)($account['label_text']?:($account['display_name']?:$account['email_address']));}}
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
$accountIds=array_map('intval',array_column($accounts,'id'));$outboxCount=0;
if($accountIds){$stmt=$pdo->prepare("SELECT COUNT(*) FROM sodium_composed_messages WHERE user_id=? AND status='scheduled' AND mail_account_id IN (".implode(',',array_fill(0,count($accountIds),'?')).')');$stmt->execute(array_merge([(int)current_user()['id']],$accountIds));$outboxCount=(int)$stmt->fetchColumn();}
$notification=null;if($newMessages>0){$detail=(string)(sodium_user_settings((int)current_user()['id'])['notification_detail']??'detailed');$notification=sodium_format_new_mail_notification($newMessages,$newMessageDetails,$newMessageAccounts,$detail);}
echo json_encode(['unified_unread'=>$total,'accounts'=>$result,'outbox_count'=>$outboxCount,'new_messages'=>$newMessages,'notification'=>$notification,'checked_at'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE);
