<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

$query=mb_substr(trim((string)($_GET['q']??'')),0,120);
$allowedScopes=['correspondents','subject','body','all'];
$scope=in_array((string)($_GET['scope']??'all'),$allowedScopes,true)?(string)($_GET['scope']??'all'):'all';
$status=in_array((string)($_GET['status']??'all'),['read','unread'],true)?(string)$_GET['status']:'all';
$cursor=max(0,(int)($_GET['cursor']??0));
if($query===''){http_response_code(422);echo json_encode(['error'=>'Recherche vide.']);exit;}

$targets=[];
foreach(sodium_accessible_mail_accounts() as $account){
    if(empty($account['password_cipher']))continue;
    foreach(sodium_account_folders($account) as $folder)$targets[]=['account'=>$account,'folder'=>$folder];
}
$total=count($targets);
if($cursor>=$total){echo json_encode(['html'=>'','next_cursor'=>$total,'done'=>true,'progress'=>$total,'total'=>$total]);exit;}
$target=$targets[$cursor];
$account=$target['account'];
$folder=$target['folder'];
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();

try{
    $folderTotal=0;
    $criteria=sodium_imap_search_criteria($query,$scope,true);
    $messages=sodium_fetch_messages($account,(string)$folder['key'],100,0,$folderTotal,$criteria,$status,false);
    foreach($messages as &$message){$message['account']=$account;$message['folder']=(string)$folder['key'];$message['folder_label']=(string)$folder['label'];}
    unset($message);
    $metadata=sodium_message_metadata($messages);
    foreach($messages as &$message){$message['metadata']=$metadata[(int)$account['id']][$message['message_key']]??['tags'=>[],'replies'=>[]];}
    unset($message);
    ob_start();
    $showSearchFolder=true;
    foreach($messages as $message)require __DIR__.'/../includes/unified-message-row.php';
    $html=(string)ob_get_clean();
    echo json_encode(['html'=>$html,'next_cursor'=>$cursor+1,'done'=>$cursor+1>=$total,'progress'=>$cursor+1,'total'=>$total,'found'=>count($messages),'folder'=>(string)$folder['label'],'account'=>(string)($account['display_name']?:$account['email_address'])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $exception){
    error_log('[Sodium deep search] account='.(int)$account['id'].' folder='.(string)$folder['key'].' '.$exception->getMessage());
    echo json_encode(['html'=>'','next_cursor'=>$cursor+1,'done'=>$cursor+1>=$total,'progress'=>$cursor+1,'total'=>$total,'found'=>0,'folder'=>(string)$folder['label'],'account'=>(string)($account['display_name']?:$account['email_address'])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
