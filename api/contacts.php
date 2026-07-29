<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

$query = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
$accountId=(int)($_GET['account_id']??0);
if (mb_strlen($query) < 1) {
    echo '[]';
    exit;
}
$like = '%' . $query . '%';
$stmt = $pdo->prepare("SELECT first_name, last_name, email, professional_email FROM users
    WHERE account_status='active' AND (
        LOWER(first_name) LIKE ? OR LOWER(last_name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(professional_email) LIKE ?
    ) ORDER BY last_name, first_name LIMIT 12");
$stmt->execute([$like, $like, $like, $like]);
$contacts = [];
foreach ($stmt->fetchAll() as $row) {
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    foreach (array_unique(array_filter([$row['professional_email'] ?? '', $row['email'] ?? ''])) as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $contacts[strtolower($email)] = ['name'=>$name ?: $email, 'email'=>$email];
    }
}
foreach (sodium_accessible_mail_accounts() as $account) {
    if (str_contains(mb_strtolower($account['email_address'] . ' ' . $account['display_name']), $query)) {
        $contacts[strtolower($account['email_address'])] = ['name'=>$account['display_name'] ?: $account['email_address'], 'email'=>$account['email_address']];
    }
}
if($accountId){
    $allowed=in_array($accountId,array_map('intval',array_column(sodium_accessible_mail_accounts(),'id')),true);
    if($allowed){$stmt=$pdo->prepare('SELECT display_name,email FROM sodium_contacts WHERE mail_account_id=? AND (LOWER(display_name) LIKE ? OR LOWER(email) LIKE ?) ORDER BY last_seen_at DESC LIMIT 20');$stmt->execute([$accountId,$like,$like]);foreach($stmt->fetchAll() as $row)$contacts[strtolower($row['email'])]=['name'=>$row['display_name']?:$row['email'],'email'=>$row['email']];}
}
echo json_encode(array_values($contacts), JSON_UNESCAPED_UNICODE);
