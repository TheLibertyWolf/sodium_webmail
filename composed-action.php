<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_login();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')redirect('/outbox.php');
$id=(int)($_POST['id']??0);
$action=(string)($_POST['action']??'');
if($id&&$action==='delete'){
    $pdo->prepare("DELETE FROM sodium_composed_messages WHERE id=? AND user_id=? AND status IN ('draft','scheduled','failed')")->execute([$id,(int)current_user()['id']]);
    flash('success','Message supprimé de la boîte d’envoi.');
}elseif($id&&$action==='undo'){
    $stmt=$pdo->prepare("UPDATE sodium_composed_messages SET status='draft',scheduled_at=NULL,undo_until=NULL WHERE id=? AND user_id=? AND status='scheduled' AND undo_until>=NOW()");
    $stmt->execute([$id,(int)current_user()['id']]);
    flash($stmt->rowCount()?'success':'warning',$stmt->rowCount()?'Envoi annulé et replacé dans les brouillons.':'Le délai d’annulation est terminé.');
}elseif($id&&$action==='edit'){
    $stmt=$pdo->prepare("UPDATE sodium_composed_messages SET edit_original_scheduled_at=scheduled_at,edit_original_undo_until=undo_until,status='draft',scheduled_at=NULL,undo_until=NULL WHERE id=? AND user_id=? AND status='scheduled'");
    $stmt->execute([$id,(int)current_user()['id']]);
    $check=$pdo->prepare("SELECT status FROM sodium_composed_messages WHERE id=? AND user_id=? AND status IN ('draft','failed')");
    $check->execute([$id,(int)current_user()['id']]);
    if($check->fetchColumn())redirect('/outbox.php?compose_draft='.$id);
    flash('warning','Ce message est déjà en cours d’envoi et ne peut plus être modifié.');
}elseif($id&&$action==='to_draft'){
    $stmt=$pdo->prepare("UPDATE sodium_composed_messages SET status='draft',scheduled_at=NULL,undo_until=NULL,last_error=NULL,edit_original_scheduled_at=NULL,edit_original_undo_until=NULL WHERE id=? AND user_id=? AND status IN ('scheduled','failed')");
    $stmt->execute([$id,(int)current_user()['id']]);
    flash($stmt->rowCount()?'success':'warning',$stmt->rowCount()?'Message remis dans les brouillons.':'Ce message ne peut plus être remis en brouillon.');
}elseif($id&&$action==='send_now'){
    $check=$pdo->prepare("SELECT to_json,subject,content_html FROM sodium_composed_messages WHERE id=? AND user_id=? AND status IN ('draft','scheduled','failed')");
    $check->execute([$id,(int)current_user()['id']]);$message=$check->fetch();$recipients=$message?(json_decode((string)$message['to_json'],true)?:[]):[];
    if(!$message||!$recipients||trim((string)$message['subject'])===''||trim((string)$message['content_html'])===''){
        flash('warning',$message?'Complétez le destinataire, l’objet et le message avant l’envoi.':'Ce message est déjà en cours d’envoi.');
    }else{
        $stmt=$pdo->prepare("UPDATE sodium_composed_messages SET status='scheduled',scheduled_at=NOW(),undo_until=NULL,last_error=NULL,edit_original_scheduled_at=NULL,edit_original_undo_until=NULL WHERE id=? AND user_id=? AND status IN ('draft','scheduled','failed')");
        $stmt->execute([$id,(int)current_user()['id']]);flash($stmt->rowCount()?'success':'warning',$stmt->rowCount()?'Message placé en envoi immédiat.':'Ce message est déjà en cours d’envoi.');
    }
}elseif($id&&$action==='cancel_edit'){
    $stmt=$pdo->prepare("UPDATE sodium_composed_messages SET status='scheduled',scheduled_at=edit_original_scheduled_at,undo_until=IF(edit_original_undo_until>NOW(),edit_original_undo_until,NULL),edit_original_scheduled_at=NULL,edit_original_undo_until=NULL WHERE id=? AND user_id=? AND status='draft' AND edit_original_scheduled_at>NOW()");
    $stmt->execute([$id,(int)current_user()['id']]);
    if(!$stmt->rowCount())$pdo->prepare("UPDATE sodium_composed_messages SET edit_original_scheduled_at=NULL,edit_original_undo_until=NULL WHERE id=? AND user_id=? AND status='draft' AND edit_original_scheduled_at IS NOT NULL AND edit_original_scheduled_at<=NOW()")->execute([$id,(int)current_user()['id']]);
    if(str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')){
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['restored'=>(bool)$stmt->rowCount()],JSON_UNESCAPED_UNICODE);
        exit;
    }
}
redirect('/outbox.php');
