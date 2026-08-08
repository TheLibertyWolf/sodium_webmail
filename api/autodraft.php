<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');
$userId=(int)current_user()['id'];$accountId=(int)($_POST['mail_account_id']??0);$allowed=false;
foreach(sodium_accessible_mail_accounts() as $account)if((int)$account['id']===$accountId&&!empty($account['can_send'])){$allowed=true;break;}
if(!$allowed){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Compte expéditeur non autorisé.']);exit;}
$id=(int)($_POST['compose_id']??0);$parse=static fn(string $value):array=>array_values(array_unique(array_filter(array_map('trim',preg_split('/[,;]+/',$value)?:[]))));
$values=[json_encode($parse((string)($_POST['to_email']??''))),json_encode($parse((string)($_POST['cc_email']??''))),json_encode($parse((string)($_POST['bcc_email']??''))),mb_substr(trim((string)($_POST['subject']??'')),0,998),(string)($_POST['content']??''),(int)($_POST['signature_id']??0)?:null,in_array($_POST['priority']??'', ['high','low'],true)?$_POST['priority']:'normal'];
if($id){$stmt=$pdo->prepare("UPDATE sodium_composed_messages SET mail_account_id=?,to_json=?,cc_json=?,bcc_json=?,subject=?,content_html=?,signature_id=?,priority=?,updated_at=NOW() WHERE id=? AND user_id=? AND status='draft'");$stmt->execute(array_merge([$accountId],$values,[$id,$userId]));if(!$stmt->rowCount())$id=0;}
if(!$id){$stmt=$pdo->prepare("INSERT INTO sodium_composed_messages(user_id,mail_account_id,status,to_json,cc_json,bcc_json,subject,content_html,signature_id,priority,attachments_json) VALUES(?,?,'draft',?,?,?,?,?,?,?,'[]')");$stmt->execute(array_merge([$userId,$accountId],$values));$id=(int)$pdo->lastInsertId();}
echo json_encode(['ok'=>true,'compose_id'=>$id,'saved_at'=>date(DATE_ATOM)]);
