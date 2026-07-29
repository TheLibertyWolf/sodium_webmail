<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);echo json_encode(['error'=>'Méthode non autorisée.']);exit;}

$payload=json_decode((string)file_get_contents('php://input'),true)?:[];
$requested=is_array($payload['messages']??null)?array_slice($payload['messages'],0,200):[];
$accounts=[];foreach(sodium_accessible_mail_accounts() as $account)$accounts[(int)$account['id']]=$account;
$groups=[];
foreach($requested as $item){
    $accountId=(int)($item['account_id']??0);$uid=(int)($item['uid']??0);$folder=(string)($item['folder']??'INBOX');
    if(!$uid||!isset($accounts[$accountId])||$folder===''||strlen($folder)>250||preg_match('/[\x00-\x1F]/',$folder))continue;
    $groups[$accountId][$folder][]=$uid;
}
$states=[];
foreach($groups as $accountId=>$folders){
    foreach($folders as $folder=>$uids){
        $uids=array_values(array_unique(array_map('intval',$uids)));
        try{
            $stream=sodium_imap_open_account($accounts[$accountId],$folder);
            try{
                $overview=@imap_fetch_overview($stream,implode(',',$uids),FT_UID)?:[];
                foreach($overview as $message)$states[]=[
                    'account_id'=>(int)$accountId,
                    'folder'=>$folder,
                    'uid'=>(int)($message->uid??0),
                    'unread'=>empty($message->seen),
                    'flagged'=>!empty($message->flagged),
                ];
            }finally{imap_close($stream);}
        }catch(Throwable $exception){error_log('[Sodium visible states] account='.$accountId.' '.$exception->getMessage());}
    }
}
echo json_encode(['states'=>$states],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
