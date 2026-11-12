<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$stmt=$pdo->prepare("SELECT id,title,body,account_line,target_url FROM sodium_push_notifications WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR) ORDER BY id DESC LIMIT 1");
$stmt->execute([(int)current_user()['id']]);$notification=$stmt->fetch();
if(!$notification){http_response_code(404);echo json_encode(['error'=>'Aucune notification disponible.']);exit;}
echo json_encode(['id'=>(int)$notification['id'],'title'=>(string)$notification['title'],'body'=>(string)$notification['body'],'account_line'=>(string)$notification['account_line'],'url'=>(string)$notification['target_url']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
