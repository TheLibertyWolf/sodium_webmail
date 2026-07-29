<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
$accountId=(int)($_GET['account_id']??0);$folder=(string)($_GET['folder']??'INBOX');$uid=(int)($_GET['uid']??0);$part=(string)($_GET['part']??'');
$account=null;foreach(sodium_accessible_mail_accounts() as $candidate)if((int)$candidate['id']===$accountId){$account=$candidate;break;}
if(!$account||!$uid||$part===''){http_response_code(404);exit('Pièce jointe introuvable.');}
try{$attachment=sodium_fetch_attachment($account,$folder,$uid,$part);$name=str_replace(["\r","\n",'"'],'',$attachment['name']);$subtype=strtolower((string)preg_replace('/[^a-z0-9.+-]/i','',(string)$attachment['subtype']));if($subtype==='jpg')$subtype='jpeg';$mime=match((int)$attachment['type']){TYPEIMAGE=>'image/'.$subtype,TYPETEXT=>'text/'.$subtype,TYPEAUDIO=>'audio/'.$subtype,TYPEVIDEO=>'video/'.$subtype,TYPEAPPLICATION=>'application/'.$subtype,default=>'application/octet-stream'};$previewMime=sodium_attachment_preview_mime($name,$mime);$safeInline=$previewMime!==null;$inline=!empty($_GET['inline'])&&$safeInline;if($inline)$mime=$previewMime;header('Content-Type: '.$mime);header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.addslashes($name).'"');header('X-Content-Type-Options: nosniff');if($inline)header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");header('Content-Length: '.strlen($attachment['data']));echo $attachment['data'];}catch(Throwable $exception){http_response_code(500);exit('Téléchargement impossible.');}
