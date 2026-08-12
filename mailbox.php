<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$account = sodium_active_mail_account();
if (!$account) {
    redirect('/index.php');
}
$folderKey = (string) ($_GET['folder'] ?? 'INBOX');
$statusFilter = in_array($_GET['status'] ?? '', ['read', 'unread'], true) ? $_GET['status'] : 'all';
$tagFilter=(int)($_GET['tag_id']??0);
$searchQuery=mb_substr(trim((string)($_GET['q']??'')),0,120);
$searchCriteria=sodium_imap_search_criteria($searchQuery);
$messageLimit=in_array((int)($_GET['limit']??25),[15,25,50,100],true)?(int)($_GET['limit']??25):25;
$page=max(1,(int)($_GET['page']??1));
$messageOffset=($page-1)*$messageLimit;
$messageTotal=0;
$folders = sodium_account_folders($account);
$folderLabel = $folderKey;
foreach ($folders as $folder) {
    if ((string) $folder['key'] === $folderKey) {
        $folderLabel = (string) $folder['label'];
        break;
    }
}
$isSentFolder = sodium_folder_icon($folderKey) === 'send';
$messages = [];
$loadError = '';
try {
    $messages = sodium_fetch_messages($account, $folderKey, $messageLimit, $messageOffset, $messageTotal, $searchCriteria, $statusFilter);
} catch (Throwable $exception) {
    $loadError = $exception->getMessage();
}
$metadataInput=array_map(static fn(array $message):array=>array_merge($message,['account'=>$account]),$messages);
$metadata=sodium_message_metadata($metadataInput);
foreach($messages as &$message){$message['metadata']=$metadata[(int)$account['id']][$message['message_key']]??['tags'=>[],'replies'=>[]];}
unset($message);
if($tagFilter)$messages=array_values(array_filter($messages,static fn(array $message):bool=>in_array($tagFilter,array_map('intval',array_column($message['metadata']['tags']??[],'id')),true)));
$tagVisibility=sodium_can_manage_all('sodium_labels')?'1=1':'(created_by=? OR is_shared=1)';
$tagStmt=$pdo->prepare('SELECT * FROM sodium_tags WHERE mail_account_id=? AND '.$tagVisibility.' ORDER BY name');
$tagParams=[(int)$account['id']];if(!sodium_can_manage_all('sodium_labels'))$tagParams[]=(int)current_user()['id'];
$tagStmt->execute($tagParams);$availableTags=$tagStmt->fetchAll();

sodium_render_header($folderLabel);
?>
<div class="mail-page-shell">
    <div class="mail-toolbar">
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#composeModal" data-compose-account="<?= (int)$account['id'] ?>"><i class="bi bi-pencil-square"></i> Nouveau message</button>
        <form method="post" action="/refresh.php" data-mail-refresh><input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>"><input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/mailbox.php') ?>"><button class="btn btn-outline-secondary" title="Relever ce compte mail" aria-label="Relever ce compte mail"><i class="bi bi-arrow-repeat"></i></button></form>
        <form method="get" action="/mailbox.php" class="input-group mail-search">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>"><input type="hidden" name="folder" value="<?= e($folderKey) ?>"><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><input type="hidden" name="tag_id" value="<?= $tagFilter ?>"><input type="hidden" name="limit" value="<?= $messageLimit ?>">
            <input class="form-control" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Rechercher dans <?= e($account['email_address']) ?>" aria-label="Rechercher">
            <?php if($searchQuery!==''): ?><a class="btn btn-outline-secondary" href="<?= e('/mailbox.php?account_id='.(int)$account['id'].'&folder='.rawurlencode($folderKey).'&status='.rawurlencode($statusFilter).'&tag_id='.$tagFilter.'&limit='.$messageLimit) ?>" title="Effacer la recherche"><i class="bi bi-x-lg"></i></a><?php endif; ?>
            <button class="btn btn-outline-secondary" type="submit" title="Rechercher"><i class="bi bi-search"></i></button>
        </form>
        <?php $baseFolderUrl='/mailbox.php?account_id='.(int)$account['id'].'&folder='.rawurlencode($folderKey).'&limit='.$messageLimit.($searchQuery!==''?'&q='.rawurlencode($searchQuery):''); ?>
        <div class="btn-group btn-group-sm mail-status-filter">
            <a class="btn btn-outline-secondary <?= $statusFilter === 'all' ? 'active' : '' ?>" href="<?= e($baseFolderUrl) ?>">Tous</a>
            <a class="btn btn-outline-secondary <?= $statusFilter === 'unread' ? 'active' : '' ?>" href="<?= e($baseFolderUrl.'&status=unread') ?>">Non lus</a>
            <a class="btn btn-outline-secondary <?= $statusFilter === 'read' ? 'active' : '' ?>" href="<?= e($baseFolderUrl.'&status=read') ?>">Lus</a>
        </div>
        <?php if($availableTags): ?><select class="form-select form-select-sm tag-filter" onchange="location.href=this.value"><option value="<?= e($baseFolderUrl.'&status='.$statusFilter) ?>">Tous les tags</option><?php foreach($availableTags as $tag): ?><option value="<?= e($baseFolderUrl.'&status='.$statusFilter.'&tag_id='.(int)$tag['id']) ?>" <?= $tagFilter===(int)$tag['id']?'selected':'' ?>><?= e($tag['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
        <label class="d-flex align-items-center gap-2 ms-auto small text-muted">Afficher <select class="form-select form-select-sm w-auto" onchange="location.href=this.value"><?php foreach([15,25,50,100] as $limitOption): ?><option value="<?= e('/mailbox.php?account_id='.(int)$account['id'].'&folder='.rawurlencode($folderKey).'&status='.$statusFilter.'&tag_id='.$tagFilter.'&limit='.$limitOption.($searchQuery!==''?'&q='.rawurlencode($searchQuery):'')) ?>" <?= $messageLimit===$limitOption?'selected':'' ?>><?= $limitOption ?></option><?php endforeach; ?></select></label>
    </div>
    <?php if ($loadError): ?><div class="mail-empty-content"><i class="bi bi-exclamation-triangle"></i><h2>Connexion IMAP impossible</h2><p><?= e($loadError) ?></p></div>
    <?php elseif (!$messages): ?><div class="mail-empty-content"><i class="bi bi-envelope-open"></i><h2><?= e($folderLabel) ?></h2><p>Ce dossier ne contient aucun message.</p></div>
    <?php else: ?><form method="post" action="/bulk-action.php" class="bulk-mail-form">
        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/mailbox.php') ?>">
        <div class="bulk-actions">
            <label class="form-check mb-0"><input class="form-check-input select-all-messages" type="checkbox"> <span class="form-check-label">Tout sélectionner</span></label>
            <div class="bulk-buttons">
                <button class="btn btn-sm btn-outline-secondary" name="bulk_action" value="archive"><i class="bi bi-archive"></i> Archiver</button>
                <button class="btn btn-sm btn-outline-danger" name="bulk_action" value="trash"><i class="bi bi-trash"></i> Supprimer</button>
                <button class="btn btn-sm btn-outline-warning" name="bulk_action" value="junk"><i class="bi bi-exclamation-octagon"></i> Indésirable</button>
                <button class="btn btn-sm btn-outline-primary" name="bulk_action" value="read"><i class="bi bi-envelope-open"></i> Marquer lu</button>
                <button class="btn btn-sm btn-outline-primary" name="bulk_action" value="unread"><i class="bi bi-envelope"></i> Marquer non lu</button>
                <?php if($availableTags): ?><div class="input-group input-group-sm tag-action-group"><select class="form-select" name="tag_id"><option value="">Ajouter un tag…</option><?php foreach($availableTags as $tag): ?><option value="<?= (int)$tag['id'] ?>"><?= e($tag['name']) ?></option><?php endforeach; ?></select><button class="btn btn-outline-secondary" name="bulk_action" value="tag"><i class="bi bi-tag"></i></button></div><?php endif; ?>
                <div class="input-group input-group-sm move-group"><select class="form-select" name="target_folder"><option value="">Déplacer vers…</option><?php foreach ($folders as $destination): ?><?php if ((string)$destination['key'] !== $folderKey): ?><option value="<?= e($destination['key']) ?>"><?= e($destination['label']) ?></option><?php endif; ?><?php endforeach; ?></select><button class="btn btn-outline-primary" name="bulk_action" value="move">Déplacer</button></div>
            </div>
        </div>
        <div class="message-list">
        <?php foreach ($messages as $message):
            $selection=base64_encode(json_encode(['account'=>(int)$account['id'],'folder'=>$folderKey,'uid'=>(int)$message['uid'],'key'=>$message['message_key']]));
            $toAddresses=$message['to_addresses']??[];$ccAddresses=$message['cc_addresses']??[];$bccAddresses=$message['bcc_addresses']??[];$primaryRecipient=$toAddresses[0]??['name'=>'','email'=>'Destinataire inconnu'];$otherRecipientCount=count($ccAddresses)+count($bccAddresses);$recipientTooltip='';
            if($isSentFolder){foreach([['À','danger',$toAddresses],['Cc','primary',$ccAddresses],['Cci','warning',$bccAddresses]] as [$recipientType,$recipientColor,$recipientAddresses])foreach($recipientAddresses as $recipientAddress){$recipientText=trim((string)($recipientAddress['name']??''));if($recipientText!=='')$recipientText.=' · ';$recipientText.=(string)($recipientAddress['email']??'');$recipientTooltip.='<div class="recipient-tooltip-line"><span class="badge text-bg-'.$recipientColor.'">'.$recipientType.'</span><span>'.e($recipientText).'</span></div>';}}
        ?>
            <div class="message-row <?= $message['unread'] ? 'unread' : '' ?>" data-message-row data-account="<?= (int)$account['id'] ?>" data-folder="<?= e($folderKey) ?>" data-uid="<?= (int)$message['uid'] ?>">
                <span class="message-select-tools"><input class="form-check-input message-checkbox" type="checkbox" name="messages[]" value="<?= e($selection) ?>"><button class="message-star <?= $message['flagged']?'is-flagged':'' ?>" type="button" data-star-toggle title="<?= $message['flagged']?'Retirer des messages marqués':'Ajouter aux messages marqués' ?>"><i class="bi bi-star<?= $message['flagged']?'-fill':'' ?>"></i></button></span>
                <?php if($isSentFolder): ?><span class="message-sender message-recipient-summary" data-bs-toggle="tooltip" data-bs-html="true" data-bs-custom-class="recipient-list-tooltip" data-bs-title="<?= e($recipientTooltip?:'<span class=&quot;text-body-secondary&quot;>Aucun destinataire disponible</span>') ?>"><span class="badge text-bg-danger recipient-type-badge">À</span><span class="message-recipient-identity"><?php if(trim((string)$primaryRecipient['name'])!==''): ?><span class="message-recipient-name"><?= e($primaryRecipient['name']) ?></span><span class="message-recipient-email"><?= e($primaryRecipient['email']) ?></span><?php else: ?><span class="message-recipient-name"><?= e($primaryRecipient['email']) ?></span><?php endif; ?></span><?php if($otherRecipientCount): ?><span class="badge rounded-pill text-bg-secondary recipient-extra-count">+<?= $otherRecipientCount ?></span><?php endif; ?></span><?php else: ?><span class="message-sender"><?= e($message['from']) ?></span><?php endif; ?>
                <span class="message-subject"><button class="read-dot <?= $message['unread']?'is-unread':'' ?>" type="button" title="<?= $message['unread']?'Marquer comme lu':'Marquer comme non lu' ?>" data-read-toggle></button><?php if($message['has_attachment']): ?><i class="bi bi-paperclip message-attachment-icon" title="Ce message contient une pièce jointe"></i><?php endif; ?><button class="message-open" type="button" data-open-message><?= e($message['subject']) ?></button><?php foreach($message['metadata']['tags']??[] as $tag): ?><span class="mail-tag" style="--tag-color:<?= e($tag['color']) ?>"><?= e($tag['name']) ?></span><?php endforeach; ?><?php if(!empty($message['metadata']['replies'])):$names=array_unique(array_column($message['metadata']['replies'],'name')); ?><i class="bi bi-reply-fill replied-icon" title="Répondu par <?= e(implode(', ',$names)) ?>"></i><?php endif; ?></span>
                <time class="message-date human-date" tabindex="0" data-timestamp="<?= (int)$message['timestamp'] ?>" data-human="<?= e(sodium_human_date($message['timestamp'])) ?>" data-exact="<?= e($message['date']) ?>"><?= e(sodium_human_date($message['timestamp'])) ?></time>
            </div>
        <?php endforeach; ?>
        </div>
        <?php $pageCount=max(1,(int)ceil($messageTotal/$messageLimit)); if($pageCount>1): ?><nav class="d-flex justify-content-between align-items-center p-3 border-top" aria-label="Pagination des messages"><span class="small text-muted"><?= min($messageOffset+1,$messageTotal) ?>–<?= min($messageOffset+$messageLimit,$messageTotal) ?> sur <?= $messageTotal ?></span><div class="btn-group btn-group-sm"><?php $pageBase=$baseFolderUrl.'&status='.$statusFilter.'&tag_id='.$tagFilter; ?><a class="btn btn-outline-secondary <?= $page<=1?'disabled':'' ?>" href="<?= e($pageBase.'&page='.max(1,$page-1)) ?>">Précédent</a><span class="btn btn-outline-secondary disabled"><?= $page ?> / <?= $pageCount ?></span><a class="btn btn-outline-secondary <?= $page>=$pageCount?'disabled':'' ?>" href="<?= e($pageBase.'&page='.min($pageCount,$page+1)) ?>">Suivant</a></div></nav><?php endif; ?>
    </form><?php endif; ?>
</div>
<?php sodium_render_footer(); ?>
