<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$accounts = sodium_accessible_mail_accounts();
$statusFilter = in_array($_GET['status'] ?? '', ['read', 'unread'], true) ? $_GET['status'] : 'all';
$tagFilter = (int)($_GET['tag_id'] ?? 0);
$searchQuery = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120);
$searchCriteria = sodium_imap_search_criteria($searchQuery);
$requestedLimit = (int)($_GET['limit'] ?? 25);
$messageLimit = in_array($requestedLimit, [15,25,50,100], true) ? $requestedLimit : 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$messages = [];
$loadErrors = [];
foreach ($accounts as $account) {
    if (empty($account['password_cipher'])) continue;
    try {
        foreach (sodium_fetch_messages($account, 'INBOX', 100, 0, $accountMessageTotal, $searchCriteria, $statusFilter) as $message) {
            $message['account'] = $account;
            $message['folder'] = 'INBOX';
            $messages[] = $message;
        }
    } catch (Throwable $exception) {
        $loadErrors[] = ($account['display_name'] ?: $account['email_address']) . ' : ' . $exception->getMessage();
    }
}
usort($messages, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);
$metadata=sodium_message_metadata($messages);
foreach($messages as &$message){$aid=(int)$message['account']['id'];$message['metadata']=$metadata[$aid][$message['message_key']]??['tags'=>[],'replies'=>[]];}
unset($message);
if($tagFilter)$messages=array_values(array_filter($messages,static fn(array $message):bool=>in_array($tagFilter,array_map('intval',array_column($message['metadata']['tags']??[],'id')),true)));
$messageTotal=count($messages);
$pageCount=max(1,(int)ceil($messageTotal/$messageLimit));
if($page>$pageCount)$page=$pageCount;
$messageOffset=($page-1)*$messageLimit;
$messages=array_slice($messages,$messageOffset,$messageLimit);
$unifiedUrl=static function(array $overrides=[])use($statusFilter,$tagFilter,$messageLimit,$searchQuery):string{
    $params=['status'=>$statusFilter,'tag_id'=>$tagFilter,'limit'=>$messageLimit,'q'=>$searchQuery];
    foreach($overrides as $key=>$value)$params[$key]=$value;
    $params=array_filter($params,static fn($value,$key)=>!($key==='status'&&$value==='all')&&!($key==='tag_id'&&(int)$value===0)&&!($key==='page'&&(int)$value<=1),ARRAY_FILTER_USE_BOTH);
    return '/index.php'.($params?'?'.http_build_query($params):'');
};
$availableTags=[];
$accountIds=array_map('intval',array_column($accounts,'id'));
if($accountIds){$tagVisibility=sodium_can_manage_all('sodium_labels')?'1=1':'(t.created_by=? OR t.is_shared=1)';$stmt=$pdo->prepare('SELECT t.*,a.email_address FROM sodium_tags t INNER JOIN sodium_mail_accounts a ON a.id=t.mail_account_id WHERE t.mail_account_id IN ('.implode(',',array_fill(0,count($accountIds),'?')).') AND '.$tagVisibility.' ORDER BY a.email_address,t.name');$tagParams=$accountIds;if(!sodium_can_manage_all('sodium_labels'))$tagParams[]=(int)current_user()['id'];$stmt->execute($tagParams);$availableTags=$stmt->fetchAll();}
sodium_render_header('Boîte de réception unifiée');
?>
<div class="mail-page-shell">
    <div class="mail-toolbar">
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#composeModal"><i class="bi bi-pencil-square"></i> Nouveau message</button>
        <form method="post" action="/refresh.php" data-mail-refresh><input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/index.php') ?>"><button class="btn btn-outline-secondary" title="Relever le courrier" aria-label="Relever le courrier"><i class="bi bi-arrow-repeat"></i></button></form>
        <form method="get" action="/index.php" class="input-group mail-search">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>"><input type="hidden" name="tag_id" value="<?= $tagFilter ?>"><input type="hidden" name="limit" value="<?= $messageLimit ?>">
            <input class="form-control" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Rechercher dans toutes les boîtes" aria-label="Rechercher">
            <?php if($searchQuery!==''): ?><a class="btn btn-outline-secondary" href="<?= e($unifiedUrl(['q'=>'','page'=>1])) ?>" title="Effacer la recherche"><i class="bi bi-x-lg"></i></a><?php endif; ?>
            <button class="btn btn-outline-secondary" type="submit" title="Rechercher"><i class="bi bi-search"></i></button>
        </form>
        <div class="btn-group btn-group-sm mail-status-filter">
            <a class="btn btn-outline-secondary <?= $statusFilter === 'all' ? 'active' : '' ?>" href="<?= e($unifiedUrl(['status'=>'all','page'=>1])) ?>">Tous</a>
            <a class="btn btn-outline-secondary <?= $statusFilter === 'unread' ? 'active' : '' ?>" href="<?= e($unifiedUrl(['status'=>'unread','page'=>1])) ?>">Non lus</a>
            <a class="btn btn-outline-secondary <?= $statusFilter === 'read' ? 'active' : '' ?>" href="<?= e($unifiedUrl(['status'=>'read','page'=>1])) ?>">Lus</a>
        </div>
        <?php if($availableTags): ?><select class="form-select form-select-sm tag-filter" onchange="location.href=this.value"><option value="<?= e($unifiedUrl(['tag_id'=>0,'page'=>1])) ?>">Tous les tags</option><?php foreach($availableTags as $tag): ?><option value="<?= e($unifiedUrl(['tag_id'=>(int)$tag['id'],'page'=>1])) ?>" <?= $tagFilter===(int)$tag['id']?'selected':'' ?>><?= e($tag['name']) ?> — <?= e($tag['email_address']) ?></option><?php endforeach; ?></select><?php endif; ?>
        <label class="d-flex align-items-center gap-2 ms-auto small text-muted">Afficher <select class="form-select form-select-sm w-auto" onchange="location.href=this.value"><?php foreach([15,25,50,100] as $limitOption): ?><option value="<?= e($unifiedUrl(['limit'=>$limitOption,'page'=>1])) ?>" <?= $messageLimit===$limitOption?'selected':'' ?>><?= $limitOption ?></option><?php endforeach; ?></select></label>
    </div>
    <?php if ($loadErrors): ?><div class="alert alert-warning m-3"><?= e(implode(' · ', $loadErrors)) ?></div><?php endif; ?>
    <?php if (!$messages): ?><div class="mail-empty-content"><i class="bi bi-inboxes"></i><h2>Boîte de réception unifiée</h2><p>Aucun message ne correspond au filtre sélectionné.</p></div>
    <?php else: ?><form method="post" action="/bulk-action.php" class="bulk-mail-form">
        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/index.php') ?>">
        <div class="bulk-actions">
            <label class="form-check mb-0"><input class="form-check-input select-all-messages" type="checkbox"> <span class="form-check-label">Tout sélectionner</span></label>
            <div class="bulk-buttons">
                <button class="btn btn-sm btn-outline-secondary" name="bulk_action" value="archive"><i class="bi bi-archive"></i> Archiver</button>
                <button class="btn btn-sm btn-outline-danger" name="bulk_action" value="trash"><i class="bi bi-trash"></i> Supprimer</button>
                <button class="btn btn-sm btn-outline-warning" name="bulk_action" value="junk"><i class="bi bi-exclamation-octagon"></i> Indésirable</button>
                <button class="btn btn-sm btn-outline-primary" name="bulk_action" value="read"><i class="bi bi-envelope-open"></i> Marquer lu</button>
                <button class="btn btn-sm btn-outline-primary" name="bulk_action" value="unread"><i class="bi bi-envelope"></i> Marquer non lu</button>
                <?php if($availableTags): ?><div class="input-group input-group-sm tag-action-group"><select class="form-select" name="tag_id"><option value="">Ajouter un tag…</option><?php foreach($availableTags as $tag): ?><option value="<?= (int)$tag['id'] ?>"><?= e($tag['name']) ?> — <?= e($tag['email_address']) ?></option><?php endforeach; ?></select><button class="btn btn-outline-secondary" name="bulk_action" value="tag"><i class="bi bi-tag"></i></button></div><?php endif; ?>
                <div class="input-group input-group-sm move-group"><select class="form-select" name="target_folder"><option value="">Déplacer vers…</option><option value="__archive">Archives</option><option value="__drafts">Brouillons</option><option value="__sent">Envoyés</option><option value="__junk">Indésirables</option><option value="__trash">Corbeille</option></select><button class="btn btn-outline-primary" name="bulk_action" value="move">Déplacer</button></div>
            </div>
        </div>
        <div class="message-list">
        <?php foreach ($messages as $message): $account=$message['account']; $selection=base64_encode(json_encode(['account'=>(int)$account['id'],'folder'=>'INBOX','uid'=>(int)$message['uid'],'key'=>$message['message_key']])); ?>
            <div class="message-row unified <?= $message['unread'] ? 'unread' : '' ?>" data-message-row data-account="<?= (int)$account['id'] ?>" data-folder="INBOX" data-uid="<?= (int)$message['uid'] ?>">
                <span class="message-select-tools"><input class="form-check-input message-checkbox" type="checkbox" name="messages[]" value="<?= e($selection) ?>"><button class="message-star <?= $message['flagged']?'is-flagged':'' ?>" type="button" data-star-toggle title="<?= $message['flagged']?'Retirer des messages marqués':'Ajouter aux messages marqués' ?>"><i class="bi bi-star<?= $message['flagged']?'-fill':'' ?>"></i></button></span>
                <span class="message-account-identity"><?php if (!empty($account['icon_path'])): ?><img class="mail-account-image" style="--account-color:<?= e($account['label_color']) ?>" src="<?= e($account['icon_path']) ?>" alt=""><?php else: ?><span class="mail-account-avatar" style="--account-color:<?= e($account['label_color']) ?>"><?= e(strtoupper(substr((string)$account['email_address'],0,1))) ?></span><?php endif; ?><?php if(!empty($account['label_text'])): ?><span class="mailbox-label" style="--label-color:<?= e($account['label_color']) ?>;--label-text-color:<?= e(sodium_color_contrast((string)$account['label_color'])) ?>"><?= e($account['label_text']) ?></span><?php endif; ?></span>
                <span class="message-sender"><?= e($message['from']) ?></span>
                <span class="message-subject"><button class="read-dot <?= $message['unread']?'is-unread':'' ?>" type="button" title="<?= $message['unread']?'Marquer comme lu':'Marquer comme non lu' ?>" data-read-toggle></button><?php if($message['has_attachment']): ?><i class="bi bi-paperclip message-attachment-icon" title="Ce message contient une pièce jointe"></i><?php endif; ?><button class="message-open" type="button" data-open-message><?= e($message['subject']) ?></button><?php foreach($message['metadata']['tags']??[] as $tag): ?><span class="mail-tag" style="--tag-color:<?= e($tag['color']) ?>"><?= e($tag['name']) ?></span><?php endforeach; ?><?php if(!empty($message['metadata']['replies'])):$names=array_unique(array_column($message['metadata']['replies'],'name')); ?><i class="bi bi-reply-fill replied-icon" title="Répondu par <?= e(implode(', ',$names)) ?>"></i><?php endif; ?></span>
                <time class="message-date human-date" tabindex="0" data-human="<?= e(sodium_human_date($message['timestamp'])) ?>" data-exact="<?= e($message['date']) ?>"><?= e(sodium_human_date($message['timestamp'])) ?></time>
            </div>
        <?php endforeach; ?>
        </div>
        <?php if($pageCount>1): ?><nav class="d-flex justify-content-between align-items-center p-3 border-top" aria-label="Pagination des messages"><span class="small text-muted"><?= min($messageOffset+1,$messageTotal) ?>–<?= min($messageOffset+$messageLimit,$messageTotal) ?> sur <?= $messageTotal ?></span><div class="btn-group btn-group-sm"><a class="btn btn-outline-secondary <?= $page<=1?'disabled':'' ?>" href="<?= e($unifiedUrl(['page'=>max(1,$page-1)])) ?>">Précédent</a><span class="btn btn-outline-secondary disabled"><?= $page ?> / <?= $pageCount ?></span><a class="btn btn-outline-secondary <?= $page>=$pageCount?'disabled':'' ?>" href="<?= e($unifiedUrl(['page'=>min($pageCount,$page+1)])) ?>">Suivant</a></div></nav><?php endif; ?>
    </form><?php endif; ?>
</div>
<?php sodium_render_footer(); ?>
