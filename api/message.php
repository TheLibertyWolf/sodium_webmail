<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

$accountId=(int)($_GET['account_id']??0);
$folder=(string)($_GET['folder']??'INBOX');
$uid=(int)($_GET['uid']??0);
$account=null;
foreach(sodium_accessible_mail_accounts() as $candidate) if((int)$candidate['id']===$accountId){$account=$candidate;break;}
if(!$account||!$uid){http_response_code(404);echo json_encode(['error'=>'Message introuvable.']);exit;}
try{
    sodium_set_seen($account,$folder,[$uid],true);
    sodium_refresh_account_cache($accountId,true);
    $message=sodium_fetch_message_content($account,$folder,$uid);
    $message['remote_images_blocked']=str_contains((string)$message['html'],'data-sodium-src=');
    $message['images_allowed']=false;
    if($message['remote_images_blocked']&&!empty($message['reply_email'])){
        $imagePreference=$pdo->prepare('SELECT 1 FROM sodium_remote_image_senders WHERE user_id=? AND sender_email=?');
        $imagePreference->execute([(int)current_user()['id'],strtolower((string)$message['reply_email'])]);
        if($imagePreference->fetchColumn()){
            $message['html']=sodium_unblock_remote_images((string)$message['html']);
            $message['images_allowed']=true;
            $message['remote_images_blocked']=false;
        }
    }
    $message['account_id']=$accountId;
    $message['folder']=$folder;
    $message['account_name']=$account['display_name']?:$account['email_address'];
    $message['account_email']=strtolower((string)$account['email_address']);
    foreach($message['attachments'] as &$attachment){
        $attachment['inline']=!empty($attachment['content_id']);
        $previewMime=sodium_attachment_preview_mime((string)$attachment['name'],(string)$attachment['mime']);
        if($previewMime!==null)$attachment['mime']=$previewMime;
        $baseUrl='/api/attachment.php?account_id='.$accountId.'&folder='.rawurlencode($folder).'&uid='.$uid.'&part='.rawurlencode($attachment['part']);
        $attachment['url']=$baseUrl.($attachment['inline']?'&inline=1':'');
        $attachment['download_url']=$baseUrl;
        $attachment['previewable']=$previewMime!==null;
        $attachment['preview_url']=$attachment['previewable']?$baseUrl.'&inline=1':'';
    }
    unset($attachment);
    $message['attachments_zip_url']='/api/attachments-zip.php?account_id='.$accountId.'&folder='.rawurlencode($folder).'&uid='.$uid;
    $cidUrls=[];
    foreach($message['attachments'] as $attachment)if(!empty($attachment['content_id']))$cidUrls[strtolower((string)$attachment['content_id'])]=$attachment['url'];
    if($cidUrls){
        $message['html']=preg_replace_callback('/(\ssrc\s*=\s*["\'])cid:([^"\']+)(["\'])/i',static function(array $match)use($cidUrls):string{
            $cid=strtolower(rawurldecode(trim($match[2],"<> \t\n\r")));
            return isset($cidUrls[$cid])?$match[1].$cidUrls[$cid].$match[3]:$match[0];
        },(string)$message['html'])??$message['html'];
    }
    $metadata=sodium_message_metadata([array_merge($message,['account'=>$account])]);
    $message['tags']=$metadata[$accountId][$message['message_key']]['tags']??[];
    $message['replies']=$metadata[$accountId][$message['message_key']]['replies']??[];
    $replyStmt=$pdo->prepare('SELECT r.id,r.subject,r.content_html,r.replied_at,r.user_id,
        u.first_name,u.last_name,u.username
        FROM sodium_message_replies r
        INNER JOIN users u ON u.id=r.user_id
        WHERE r.mail_account_id=? AND r.source_message_key=?
        ORDER BY r.replied_at ASC,r.id ASC');
    $replyStmt->execute([$accountId,$message['message_key']]);
    $message['reply_messages']=array_map(static function(array $reply):array{
        $name=trim((string)($reply['first_name']??'').' '.(string)($reply['last_name']??''))?:((string)($reply['username']??'Utilisateur'));
        return [
            'id'=>(int)$reply['id'],
            'subject'=>(string)$reply['subject'],
            'html'=>(string)($reply['content_html']??''),
            'date'=>(string)$reply['replied_at'],
            'author'=>$name,
            'is_mine'=>(int)$reply['user_id']===(int)(current_user()['id']??0),
        ];
    },$replyStmt->fetchAll());
    $message['unread_status']=sodium_unread_snapshot();
    echo json_encode($message,JSON_UNESCAPED_UNICODE);
}catch(Throwable $exception){error_log('[Sodium message] '.$exception->getMessage());http_response_code(500);echo json_encode(['error'=>'Le message ne peut pas être ouvert pour le moment.'],JSON_UNESCAPED_UNICODE);}
