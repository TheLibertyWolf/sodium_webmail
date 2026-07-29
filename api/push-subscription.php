<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    try{echo json_encode(['public_key'=>sodium_vapid_details()['public']],JSON_UNESCAPED_SLASHES);}
    catch(Throwable $exception){error_log('[Sodium push key] '.$exception->getMessage());http_response_code(503);echo json_encode(['error'=>'Notifications Push indisponibles.']);}
    exit;
}
$payload=json_decode((string)file_get_contents('php://input'),true)?:[];
$endpoint=trim((string)($payload['endpoint']??''));
$enabled=!empty($payload['enabled']);
if(!filter_var($endpoint,FILTER_VALIDATE_URL)||!str_starts_with($endpoint,'https://')){http_response_code(422);echo json_encode(['error'=>'Abonnement invalide.']);exit;}
$hash=hash('sha256',$endpoint);
if($enabled){
    $pdo->prepare('INSERT INTO sodium_push_subscriptions(user_id,endpoint,endpoint_hash,enabled) VALUES(?,?,?,1)
        ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),endpoint=VALUES(endpoint),enabled=1,updated_at=NOW()')
        ->execute([(int)current_user()['id'],$endpoint,$hash]);
}else $pdo->prepare('UPDATE sodium_push_subscriptions SET enabled=0 WHERE endpoint_hash=? AND user_id=?')->execute([$hash,(int)current_user()['id']]);
echo json_encode(['ok'=>true,'enabled'=>$enabled]);
