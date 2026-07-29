<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

$accountId=(int)($_GET['account_id']??0);
$folder=(string)($_GET['folder']??'INBOX');
$uid=(int)($_GET['uid']??0);
$account=null;
foreach(sodium_accessible_mail_accounts() as $candidate){
    if((int)$candidate['id']===$accountId){$account=$candidate;break;}
}
if(!$account||!$uid){http_response_code(404);exit('Message introuvable.');}
if(!class_exists('ZipArchive')){http_response_code(501);exit('Création de l’archive indisponible.');}

$temporaryPath=null;

try{
    $message=sodium_fetch_message_content($account,$folder,$uid);
    $attachments=array_values(array_filter($message['attachments']??[],static fn(array $attachment):bool=>empty($attachment['content_id'])));
    if(!$attachments){http_response_code(404);exit('Aucune pièce jointe à télécharger.');}

    $temporaryPath=tempnam(sys_get_temp_dir(),'sodium-attachments-');
    if($temporaryPath===false)throw new RuntimeException('Création de l’archive impossible.');
    $zip=new ZipArchive();
    if($zip->open($temporaryPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Création de l’archive impossible.');
    $usedNames=[];
    $totalSize=0;
    foreach($attachments as $index=>$metadata){
        $attachment=sodium_fetch_attachment($account,$folder,$uid,(string)$metadata['part']);
        $totalSize+=strlen($attachment['data']);
        if($totalSize>100*1024*1024)throw new RuntimeException('Les pièces jointes dépassent la limite de 100 Mo.');
        $name=trim(preg_replace('/[\/\\\\\x00-\x1F\x7F]+/u','-',(string)$attachment['name'])??'');
        if($name==='')$name='piece-jointe-'.($index+1);
        $originalName=$name;$suffix=2;
        while(isset($usedNames[mb_strtolower($name)])){
            $extension=pathinfo($originalName,PATHINFO_EXTENSION);
            $base=$extension!==''?substr($originalName,0,-strlen($extension)-1):$originalName;
            $name=$base.'-'.$suffix++.($extension!==''?'.'.$extension:'');
        }
        $usedNames[mb_strtolower($name)]=true;
        $zip->addFromString($name,$attachment['data']);
    }
    $zip->close();
    $archiveName='pieces-jointes-'.$uid.'.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$archiveName.'"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: '.filesize($temporaryPath));
    readfile($temporaryPath);
}catch(Throwable $exception){
    if(!headers_sent()){http_response_code(500);header('Content-Type: text/plain; charset=UTF-8');}
    error_log('[Sodium attachments zip] '.$exception->getMessage());
    echo 'Téléchargement des pièces jointes impossible.';
}finally{
    if(is_string($temporaryPath)&&is_file($temporaryPath))unlink($temporaryPath);
}
