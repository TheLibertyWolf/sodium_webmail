<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');
$accountId=(int)($_POST['account_id']??0);$folder=(string)($_POST['folder']??'INBOX');$uid=(int)($_POST['uid']??0);$seen=($_POST['seen']??'1')==='1';$flagged=($_POST['flagged']??'1')==='1';
$account=null;foreach(sodium_accessible_mail_accounts() as $candidate)if((int)$candidate['id']===$accountId){$account=$candidate;break;}
if(!$account||!$uid){http_response_code(404);echo '{"ok":false}';exit;}
try{
    if(isset($_POST['flagged'])){
        sodium_set_flagged($account,$folder,[$uid],$flagged);
        echo json_encode(['ok'=>true,'flagged'=>$flagged]);
    }else{
        sodium_set_seen($account,$folder,[$uid],$seen);
        sodium_refresh_account_cache($accountId,true);
        echo json_encode(['ok'=>true,'seen'=>$seen,'unread_status'=>sodium_unread_snapshot()]);
    }
}catch(Throwable $exception){error_log('[Sodium message-state] '.$exception->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Action impossible sur ce message.']);}
