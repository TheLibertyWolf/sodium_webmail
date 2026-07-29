<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/index.php');

$accountId = (int) ($_POST['mail_account_id'] ?? 0);
$accounts = sodium_accessible_mail_accounts();
$account = null;
foreach ($accounts as $candidate) {
    if ((int) $candidate['id'] === $accountId && !empty($candidate['can_send'])) {
        $account = $candidate;
        break;
    }
}
if (!$account) {
    flash('danger', 'Envoi non autorisé depuis ce compte.');
    redirect('/index.php');
}

$parseRecipients = static fn(string $value): array => array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;]+/', $value) ?: []))));
$recipients = $parseRecipients((string) ($_POST['to_email'] ?? ''));
$cc = $parseRecipients((string) ($_POST['cc_email'] ?? ''));
$bcc = $parseRecipients((string) ($_POST['bcc_email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$content = trim((string) ($_POST['content'] ?? ''));
$composeAction = in_array($_POST['compose_action'] ?? '', ['draft', 'schedule', 'send'], true) ? (string) $_POST['compose_action'] : 'send';
$composeId = (int)($_POST['compose_id'] ?? 0);
$userMessageSettings=sodium_user_settings((int)current_user()['id']);
$sendDelay=max(1,min(60,(int)$userMessageSettings['send_delay']));
$inReplyTo=preg_match('/^<[^<>\\s]+>$/',(string)($_POST['reply_message_id']??''))?(string)$_POST['reply_message_id']:'';
if ($composeAction !== 'draft' && (!$recipients || $subject === '' || $content === '')) {
    flash('danger', 'Destinataire, objet et message sont obligatoires.');
    redirect('/index.php');
}
foreach (array_merge($recipients, $cc, $bcc) as $recipient) {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Adresse destinataire invalide : ' . $recipient);
        redirect('/index.php');
    }
}

$attachments=[];$totalSize=0;
if(!empty($_FILES['attachments']['tmp_name'])&&is_array($_FILES['attachments']['tmp_name'])){
    foreach($_FILES['attachments']['tmp_name'] as $index=>$tmp){
        if(!is_uploaded_file($tmp)||($_FILES['attachments']['error'][$index]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;
        $size=(int)($_FILES['attachments']['size'][$index]??0);$totalSize+=$size;
        if($totalSize>25*1024*1024){flash('danger','Les pièces jointes dépassent 25 Mo.');redirect('/index.php');}
        $attachments[]=['name'=>(string)$_FILES['attachments']['name'][$index],'type'=>(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream','data'=>(string)file_get_contents($tmp)];
    }
}
$forwardAccountId=(int)($_POST['forward_account_id']??0);
$forwardFolder=(string)($_POST['forward_folder']??'');
$forwardUid=(int)($_POST['forward_uid']??0);
$forwardParts=array_values(array_unique(array_filter((array)($_POST['forward_parts']??[]),static fn($part):bool=>is_string($part)&&preg_match('/^\d+(?:\.\d+)*$/',$part)===1)));
if($forwardAccountId&&$forwardUid&&$forwardFolder!==''&&$forwardParts){
    $forwardAccount=null;
    foreach($accounts as $candidate)if((int)$candidate['id']===$forwardAccountId){$forwardAccount=$candidate;break;}
    if(!$forwardAccount||strlen($forwardFolder)>255||preg_match('/[\x00-\x1F\x7F]/',$forwardFolder)){flash('danger','Source du transfert invalide.');redirect('/index.php');}
    foreach(array_slice($forwardParts,0,30) as $partNumber){
        try{$file=sodium_fetch_attachment($forwardAccount,$forwardFolder,$forwardUid,$partNumber);}catch(Throwable $exception){error_log('[Sodium forward attachment] '.$exception->getMessage());continue;}
        $size=strlen((string)$file['data']);$totalSize+=$size;
        if($totalSize>25*1024*1024){flash('danger','Les pièces jointes dépassent 25 Mo.');redirect('/index.php');}
        $subtype=strtolower((string)($file['subtype']??'octet-stream'));
        if($subtype==='jpg')$subtype='jpeg';
        $mime=match((int)($file['type']??TYPEAPPLICATION)){TYPEIMAGE=>'image/'.$subtype,TYPETEXT=>'text/'.$subtype,TYPEAUDIO=>'audio/'.$subtype,TYPEVIDEO=>'video/'.$subtype,TYPEAPPLICATION=>'application/'.$subtype,default=>'application/octet-stream'};
        $attachments[]=['name'=>(string)$file['name'],'type'=>$mime,'data'=>(string)$file['data']];
    }
}
if($composeId&&!$attachments){
    $existingAttachmentStmt=$pdo->prepare("SELECT attachments_json FROM sodium_composed_messages WHERE id=? AND user_id=? AND status IN ('draft','failed')");
    $existingAttachmentStmt->execute([$composeId,(int)current_user()['id']]);
    foreach(json_decode((string)($existingAttachmentStmt->fetchColumn()?:'[]'),true)?:[] as $storedFile){
        $decoded=base64_decode((string)($storedFile['data']??''),true);
        if($decoded!==false)$attachments[]=['name'=>(string)($storedFile['name']??'piece-jointe'),'type'=>(string)($storedFile['type']??'application/octet-stream'),'data'=>$decoded];
    }
}

$senderName = trim((string) ((current_user()['first_name'] ?? '') . ' ' . (current_user()['last_name'] ?? '')));
$signatureId = (int) ($_POST['signature_id'] ?? 0);
if ($signatureId) {
    $stmt = $pdo->prepare('SELECT sender_name FROM sodium_signatures WHERE id=? AND mail_account_id=? AND (user_id=? OR is_shared=1)');
    $stmt->execute([$signatureId, $accountId, (int) current_user()['id']]);
    $identity = $stmt->fetch();
    if ($identity) {
        $senderName = trim((string) $identity['sender_name']) ?: $senderName;
    }
}
$senderName = $senderName !== '' ? $senderName : (string) ($account['display_name'] ?: $account['email_address']);
$body = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.55;color:#1f2937">'
    . sodium_sanitize_email_html($content)
    . '</div>';

$priority=in_array($_POST['priority']??'',['high','low'],true)?$_POST['priority']:'normal';
$returnTo = (string) ($_POST['return_to'] ?? '/index.php');
$returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/index.php';
if($composeAction==='draft'||$composeAction==='schedule'||$composeAction==='send'){
    $scheduledAt=null;
    if($composeAction==='schedule'){
        try{$scheduledDate=new DateTimeImmutable((string)($_POST['scheduled_at']??''),new DateTimeZone('Europe/Paris'));}catch(Throwable){$scheduledDate=false;}
        if(!$scheduledDate||$scheduledDate<=new DateTimeImmutable('now',new DateTimeZone('Europe/Paris'))){flash('danger','Choisissez une date d’envoi future.');redirect($returnTo);}
        $scheduledAt=$scheduledDate->format('Y-m-d H:i:s');
    }elseif($composeAction==='send'){
        $scheduledDate=new DateTimeImmutable('+'.$sendDelay.' seconds',new DateTimeZone('Europe/Paris'));
        $scheduledAt=$scheduledDate->format('Y-m-d H:i:s');
    }
    $storedAttachments=array_map(static fn(array $file):array=>['name'=>$file['name'],'type'=>$file['type'],'data'=>base64_encode($file['data'])],$attachments);
    $replyAccountId=(int)($_POST['reply_account_id']??0);
    $replyKey=preg_match('/^[a-f0-9]{64}$/',(string)($_POST['reply_message_key']??''))?(string)$_POST['reply_message_key']:null;
    if($replyAccountId!==$accountId){$replyAccountId=0;$replyKey=null;}
    if($composeId){
        $stmt=$pdo->prepare("UPDATE sodium_composed_messages SET mail_account_id=?,status=?,to_json=?,cc_json=?,bcc_json=?,subject=?,content_html=?,signature_id=?,priority=?,attachments_json=?,scheduled_at=?,reply_account_id=?,reply_message_key=?,in_reply_to=?,undo_until=?,last_error=NULL,edit_original_scheduled_at=NULL,edit_original_undo_until=NULL WHERE id=? AND user_id=? AND status IN ('draft','failed')");
        $stmt->execute([$accountId,$composeAction==='draft'?'draft':'scheduled',json_encode($recipients),json_encode($cc),json_encode($bcc),$subject,$content,$signatureId?:null,$priority,json_encode($storedAttachments),$scheduledAt,$replyAccountId?:null,$replyKey,$inReplyTo?:null,$composeAction==='send'?$scheduledAt:null,$composeId,(int)current_user()['id']]);
    }else{
        $stmt=$pdo->prepare('INSERT INTO sodium_composed_messages
            (user_id,mail_account_id,status,to_json,cc_json,bcc_json,subject,content_html,signature_id,priority,attachments_json,scheduled_at,reply_account_id,reply_message_key,in_reply_to,undo_until)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([(int)current_user()['id'],$accountId,$composeAction==='draft'?'draft':'scheduled',json_encode($recipients),json_encode($cc),json_encode($bcc),$subject,$content,$signatureId?:null,$priority,json_encode($storedAttachments),$scheduledAt,$replyAccountId?:null,$replyKey,$inReplyTo?:null,$composeAction==='send'?$scheduledAt:null]);
    }
    if($composeAction==='send'){
        flash('success','Message placé en attente pendant '.$sendDelay.' seconde'.($sendDelay>1?'s':'').'. Vous pouvez encore l’annuler depuis la boîte d’envoi.');
        redirect('/outbox.php');
    }
    flash('success',$composeAction==='draft'?'Brouillon enregistré.':'Envoi programmé pour le '.$scheduledDate->format('d/m/Y à H:i').'.');
    redirect($returnTo);
}

try {
    sodium_send_smtp_message($account, $recipients, $cc, $bcc, $subject, $body, $attachments, $priority, $senderName, $inReplyTo);
    if($composeId)$pdo->prepare("UPDATE sodium_composed_messages SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=? AND user_id=?")->execute([$composeId,(int)current_user()['id']]);
    $replyAccountId=(int)($_POST['reply_account_id']??0);$replyKey=(string)($_POST['reply_message_key']??'');
    if($replyAccountId===$accountId&&preg_match('/^[a-f0-9]{64}$/',$replyKey)){
        $pdo->prepare('INSERT INTO sodium_message_replies (mail_account_id,source_message_key,user_id,subject,content_html) VALUES (?,?,?,?,?)')
            ->execute([$accountId,$replyKey,(int)current_user()['id'],$subject,$body]);
    }
    flash('success', count(array_merge($recipients, $cc, $bcc)) > 1 ? 'Message envoyé aux destinataires.' : 'Message envoyé.');
} catch (Throwable $exception) {
    $smtpError = 'Envoi impossible depuis ' . $account['email_address'] . ' : ' . ($exception->getMessage() ?: 'erreur SMTP sans détail.');
    error_log('[Sodium SMTP] account=' . $account['email_address'] . ' host=' . $account['smtp_host'] . ':' . $account['smtp_port'] . ' error=' . $exception->getMessage());
    flash('danger', $smtpError);
}

redirect($returnTo);
