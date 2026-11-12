<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

$accountId=(int)($_POST['account_id']??0);
$action=(string)($_POST['action']??'');
$folder=(string)($_POST['folder']??'');
$name=trim(preg_replace('/\s+/u',' ',(string)($_POST['name']??''))??'');
$target=(string)($_POST['target_folder']??'');
$account=null;
foreach(sodium_accessible_mail_accounts() as $candidate)if((int)$candidate['id']===$accountId){$account=$candidate;break;}
if(!$account||!in_array($action,['create','rename','delete'],true)){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Compte mail ou action introuvable.'],JSON_UNESCAPED_UNICODE);exit;}

$fail=static function(string $message,int $status=422):never{http_response_code($status);echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);exit;};
$folders=sodium_account_folders($account);
$folderKeys=array_map(static fn(array $item):string=>(string)$item['key'],$folders);
if($action!=='create'&&(!in_array($folder,$folderKeys,true)||sodium_folder_is_protected($folder)))$fail('Ce dossier système ne peut pas être modifié.');
if($action==='delete'&&$target!==''&&($target===$folder||!in_array($target,$folderKeys,true)))$fail('Le dossier de destination est invalide.');

$control=null;
try{
    $control=sodium_imap_open_account($account,'INBOX',false);
    $root=sodium_imap_mailbox($account);
    $mailboxes=@imap_getmailboxes($control,$root,'*')?:[];
    $delimiter='.';$serverKeys=[];
    foreach($mailboxes as $mailbox){
        if(!empty($mailbox->delimiter))$delimiter=(string)$mailbox->delimiter;
        $full=(string)$mailbox->name;
        $serverKeys[] = str_starts_with($full,$root)?substr($full,strlen($root)):$full;
    }
    if($action==='create'||$action==='rename'){
        if($name===''||mb_strlen($name)>80||preg_match('/[\x00-\x1F\x7F]/u',$name)||str_contains($name,$delimiter))$fail('Saisissez un nom de dossier valide, sans caractère de hiérarchie.');
        $encoded=mb_convert_encoding($name,'UTF7-IMAP','UTF-8');
        if($action==='create'){
            $prefix='';
            foreach($serverKeys as $key)if(str_starts_with(strtoupper($key),'INBOX'.strtoupper($delimiter))){$prefix='INBOX'.$delimiter;break;}
            $newKey=$prefix.$encoded;
            if(in_array(mb_strtolower($newKey),array_map('mb_strtolower',$serverKeys),true))$fail('Un dossier portant ce nom existe déjà.');
            if(!@imap_createmailbox($control,sodium_imap_mailbox($account,$newKey)))throw new RuntimeException('Création IMAP refusée.');
            $message='Le dossier « '.$name.' » a été créé.';
        }else{
            $position=strrpos($folder,$delimiter);
            $newKey=($position===false?'':substr($folder,0,$position+strlen($delimiter))).$encoded;
            if($newKey===$folder)$fail('Le nouveau nom est identique au nom actuel.');
            if(in_array(mb_strtolower($newKey),array_map('mb_strtolower',$serverKeys),true))$fail('Un dossier portant ce nom existe déjà.');
            if(!@imap_renamemailbox($control,sodium_imap_mailbox($account,$folder),sodium_imap_mailbox($account,$newKey)))throw new RuntimeException('Renommage IMAP refusé.');
            $message='Le dossier a été renommé « '.$name.' ».';
        }
    }else{
        if($target!==''){
            $source=sodium_imap_open_account($account,$folder,false);
            try{$uids=@imap_search($source,'ALL',SE_UID)?:[];}finally{imap_close($source);}
            if($uids)sodium_bulk_move($account,$folder,array_map('intval',$uids),$target);
        }else{
            $source=sodium_imap_open_account($account,$folder,false);
            try{
                $uids=@imap_search($source,'ALL',SE_UID)?:[];
                if($uids){@imap_setflag_full($source,implode(',',array_map('intval',$uids)),'\\Deleted',ST_UID);@imap_expunge($source);}
            }finally{imap_close($source);}
        }
        if(!@imap_deletemailbox($control,sodium_imap_mailbox($account,$folder)))throw new RuntimeException('Suppression IMAP refusée.');
        $message=$target!==''?'Les messages ont été déplacés et le dossier supprimé.':'Le dossier et son contenu ont été supprimés.';
    }
    imap_close($control);$control=null;
    sodium_refresh_account_cache($accountId,true);
    echo json_encode(['ok'=>true,'message'=>$message],JSON_UNESCAPED_UNICODE);
}catch(Throwable $exception){
    if(is_resource($control)||$control instanceof IMAP\Connection)imap_close($control);
    error_log('[Sodium folder action] account='.$accountId.' action='.$action.' '.$exception->getMessage());
    http_response_code(500);echo json_encode(['ok'=>false,'error'=>'L’opération sur le dossier a été refusée par le serveur mail.'],JSON_UNESCAPED_UNICODE);
}
