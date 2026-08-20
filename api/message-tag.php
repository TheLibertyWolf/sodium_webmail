<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée.']);exit;
}
$accountId=(int)($_POST['account_id']??0);
$messageKey=(string)($_POST['message_key']??'');
$tagId=(int)($_POST['tag_id']??0);
$account=null;
foreach(sodium_accessible_mail_accounts() as $candidate)if((int)$candidate['id']===$accountId){$account=$candidate;break;}
if(!$account||!preg_match('/^[a-f0-9]{64}$/',$messageKey)||!$tagId){
    http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Message ou tag introuvable.']);exit;
}
$visibility=sodium_can_manage_all('sodium_labels')?'1=1':'(created_by=? OR is_shared=1)';
$stmt=$pdo->prepare('SELECT t.id FROM sodium_tags t INNER JOIN sodium_tag_accounts ta ON ta.tag_id=t.id WHERE t.id=? AND ta.mail_account_id=? AND '.$visibility);
$params=[$tagId,$accountId];if(!sodium_can_manage_all('sodium_labels'))$params[]=(int)current_user()['id'];
$stmt->execute($params);
if(!$stmt->fetchColumn()){
    http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Ce tag n’est pas accessible.']);exit;
}
$pdo->prepare('DELETE FROM sodium_message_tags WHERE mail_account_id=? AND message_key=? AND tag_id=?')->execute([$accountId,$messageKey,$tagId]);
echo json_encode(['ok'=>true,'tag_id'=>$tagId]);
