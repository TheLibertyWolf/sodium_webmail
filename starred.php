<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$accounts=sodium_accessible_mail_accounts();
$messages=[];$loadErrors=[];
foreach($accounts as $account){
    if(empty($account['password_cipher']))continue;
    foreach(sodium_account_folders($account) as $folder){
        $folderKey=(string)($folder['key']??'');
        if($folderKey===''||in_array(sodium_folder_icon($folderKey),['exclamation-octagon','trash'],true))continue;
        try{
            foreach(sodium_fetch_messages($account,$folderKey,100,0,$folderTotal,'FLAGGED') as $message){
                $message['account']=$account;$message['folder']=$folderKey;$messages[]=$message;
            }
        }catch(Throwable $exception){$loadErrors[]=$account['email_address'].' · '.$folderKey;}
    }
}
usort($messages,static fn(array $a,array $b):int=>($b['timestamp']<=>$a['timestamp'])?:($b['uid']<=>$a['uid']));
$messages=array_slice($messages,0,250);
$metadata=sodium_message_metadata($messages);
foreach($messages as &$message){$aid=(int)$message['account']['id'];$message['metadata']=$metadata[$aid][$message['message_key']]??['tags'=>[],'replies'=>[]];}unset($message);

sodium_render_header('Messages marqués');
?>
<div class="mail-page-shell">
<?php if($loadErrors): ?><div class="alert alert-warning m-3">Certains dossiers n’ont pas pu être consultés.</div><?php endif; ?>
<?php if(!$messages): ?><div class="mail-empty-content"><i class="bi bi-star"></i><h2>Messages marqués</h2><p>Les messages auxquels vous ajoutez une étoile apparaîtront ici.</p></div>
<?php else: ?><div class="message-list">
<?php foreach($messages as $message):$account=$message['account'];$selection=base64_encode(json_encode(['account'=>(int)$account['id'],'folder'=>$message['folder'],'uid'=>(int)$message['uid'],'key'=>$message['message_key']])); ?>
<div class="message-row unified <?= $message['unread']?'unread':'' ?>" data-message-row data-account="<?= (int)$account['id'] ?>" data-folder="<?= e($message['folder']) ?>" data-uid="<?= (int)$message['uid'] ?>">
<span class="message-select-tools"><input class="form-check-input message-checkbox" type="checkbox" value="<?= e($selection) ?>"><button class="message-star is-flagged" type="button" data-star-toggle title="Retirer des messages marqués"><i class="bi bi-star-fill"></i></button></span>
<span class="message-account-identity"><?php if(!empty($account['icon_path'])): ?><img class="mail-account-image" style="--account-color:<?= e($account['label_color']) ?>" src="<?= e($account['icon_path']) ?>" alt=""><?php else: ?><span class="mail-account-avatar" style="--account-color:<?= e($account['label_color']) ?>"><?= e(strtoupper(substr((string)$account['email_address'],0,1))) ?></span><?php endif; ?><?php if(!empty($account['label_text'])):?><span class="mailbox-label" style="--label-color:<?=e($account['label_color'])?>;--label-text-color:<?=e(sodium_color_contrast((string)$account['label_color']))?>"><?=e($account['label_text'])?></span><?php endif;?></span>
<span class="message-sender"><?= e($message['from']) ?></span>
<span class="message-subject"><button class="read-dot <?= $message['unread']?'is-unread':'' ?>" type="button" data-read-toggle></button><?php if($message['has_attachment']): ?><i class="bi bi-paperclip message-attachment-icon" title="Ce message contient une pièce jointe"></i><?php endif; ?><button class="message-open" type="button" data-open-message><?= e($message['subject']) ?></button></span>
<time class="message-date human-date" tabindex="0" data-timestamp="<?= (int)$message['timestamp'] ?>" data-human="<?= e(sodium_human_date($message['timestamp'])) ?>" data-exact="<?= e($message['date']) ?>"><?= e(sodium_human_date($message['timestamp'])) ?></time>
</div>
<?php endforeach; ?>
</div><?php endif; ?>
</div>
<?php sodium_render_footer(); ?>
