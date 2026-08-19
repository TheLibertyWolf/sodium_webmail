<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
sodium_require_aptitude('sodium_settings_view');
require_once __DIR__ . '/includes/layout.php';

$canManage = sodium_can('sodium_settings_manage');
$currentUserId=(int)(current_user()['id']??0);
$allowedIntervals = [1,5,8,10,15,30,60];
$allowedSendDelays = [1,3,5,10,15,30,60];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) { http_response_code(403); exit('Gestion des paramètres non autorisée.'); }
    if(($_POST['action']??'')==='auto_reply'){
        $messageAccounts=sodium_accessible_mail_accounts();$accessibleIds=array_map('intval',array_column($messageAccounts,'id'));
        $id=(int)($_POST['id']??0);
        if(($_POST['rule_action']??'save')==='delete'){
            $pdo->prepare('DELETE FROM sodium_auto_reply_rules WHERE id=? AND user_id=?')->execute([$id,$currentUserId]);
            flash('success','Règle de réponse automatique supprimée.');redirect('/messages.php');
        }
        $name=mb_substr(trim((string)($_POST['name']??'')),0,190);
        $enabled=!empty($_POST['enabled'])?1:0;
        $appliesAll=!empty($_POST['applies_all'])?1:0;
        $selectedIds=array_values(array_intersect($accessibleIds,array_map('intval',array_keys($_POST['mail_accounts']??[]))));
        $subject=mb_substr(trim((string)($_POST['auto_subject']??'')),0,998);
        $content=trim((string)($_POST['auto_content_html']??''));
        if($name===''||$content===''||(!$appliesAll&&!$selectedIds)){flash('danger','Le nom, le message et au moins un compte mail sont obligatoires.');redirect('/messages.php');}
        $startsAt=trim((string)($_POST['starts_at']??''));$endsAt=trim((string)($_POST['ends_at']??''));
        $startsAt=$startsAt!==''?(new DateTimeImmutable($startsAt,new DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s'):null;
        $endsAt=$endsAt!==''?(new DateTimeImmutable($endsAt,new DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s'):null;
        if($id){
            $stmt=$pdo->prepare('UPDATE sodium_auto_reply_rules SET name=?,enabled=?,applies_all=?,starts_at=?,ends_at=?,subject=?,content_html=? WHERE id=? AND user_id=?');
            $stmt->execute([$name,$enabled,$appliesAll,$startsAt,$endsAt,$subject?:'Réponse automatique',$content,$id,$currentUserId]);
        }else{
            $pdo->prepare('INSERT INTO sodium_auto_reply_rules(user_id,name,enabled,applies_all,starts_at,ends_at,subject,content_html) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$currentUserId,$name,$enabled,$appliesAll,$startsAt,$endsAt,$subject?:'Réponse automatique',$content]);$id=(int)$pdo->lastInsertId();
        }
        $scopeIds=$appliesAll?$accessibleIds:$selectedIds;
        if(!$appliesAll)$pdo->prepare('DELETE FROM sodium_auto_reply_rule_accounts WHERE rule_id=? AND mail_account_id NOT IN ('.implode(',',array_fill(0,max(1,count($selectedIds)),'?')).')')->execute(array_merge([$id],$selectedIds?:[0]));
        foreach($messageAccounts as $account)if(in_array((int)$account['id'],$scopeIds,true)){
            $lastUid=0;try{$stream=sodium_imap_open_account($account,'INBOX');$uids=@imap_search($stream,'ALL',SE_UID)?:[];$lastUid=$uids?max(array_map('intval',$uids)):0;imap_close($stream);}catch(Throwable){}
            $pdo->prepare('INSERT IGNORE INTO sodium_auto_reply_rule_accounts(rule_id,mail_account_id,last_uid) VALUES(?,?,?)')->execute([$id,(int)$account['id'],$lastUid]);
        }
        flash('success','Réponse automatique enregistrée.');
        redirect('/messages.php');
    }
    $interval = (int)($_POST['refresh_interval'] ?? 1);
    if (!in_array($interval, $allowedIntervals, true)) $interval = 1;
    $sendDelay=(int)($_POST['send_delay']??10);
    if(!in_array($sendDelay,$allowedSendDelays,true))$sendDelay=10;
    $quoteReply = !empty($_POST['quote_reply']) ? 1 : 0;
    $signaturePosition = in_array($_POST['signature_position'] ?? '', ['before_quote','after_quote'], true)
        ? (string)$_POST['signature_position']
        : 'before_quote';
    $pdo->prepare('INSERT INTO sodium_user_settings (user_id,refresh_interval,send_delay,quote_reply,signature_position)
        VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE refresh_interval=VALUES(refresh_interval),send_delay=VALUES(send_delay),
        quote_reply=VALUES(quote_reply),signature_position=VALUES(signature_position),updated_at=NOW()')
        ->execute([$currentUserId,$interval,$sendDelay,$quoteReply,$signaturePosition]);
    flash('success', 'Préférences de messages enregistrées.');
    redirect('/messages.php');
}
$settings = sodium_user_settings($currentUserId);
$messageAccounts=sodium_accessible_mail_accounts();
$autoRules=[];$autoScope=[];
$stmt=$pdo->prepare('SELECT * FROM sodium_auto_reply_rules WHERE user_id=? ORDER BY enabled DESC,starts_at DESC,id DESC');$stmt->execute([$currentUserId]);$autoRules=$stmt->fetchAll();
if($autoRules){$ruleIds=array_map('intval',array_column($autoRules,'id'));$stmt=$pdo->prepare('SELECT rule_id,mail_account_id FROM sodium_auto_reply_rule_accounts WHERE rule_id IN ('.implode(',',array_fill(0,count($ruleIds),'?')).')');$stmt->execute($ruleIds);foreach($stmt->fetchAll() as $scope)$autoScope[(int)$scope['rule_id']][]=(int)$scope['mail_account_id'];}
$blankRule=['id'=>0,'name'=>'','enabled'=>1,'applies_all'=>0,'starts_at'=>'','ends_at'=>'','subject'=>'Réponse automatique','content_html'=>''];
sodium_render_header('Messages');
?>
<div class="table-card">
    <form method="post"><input type="hidden" name="action" value="settings">
        <div class="p-4 border-bottom">
            <h2 class="h5 mb-1"><i class="bi bi-sliders me-2"></i>Réglages des messages</h2>
            <p class="text-muted mb-0">Configurez la réception du courrier, l’envoi et le comportement des réponses.</p>
        </div>
        <div class="p-4 border-bottom">
            <h3 class="h6 mb-1"><i class="bi bi-arrow-repeat me-2"></i>Réception du courrier</h3>
            <p class="text-muted mb-3">Actualisation automatique tant que Sodium est ouvert.</p>
            <label class="form-label" for="refreshInterval">Délai de relève du courrier</label>
            <select class="form-select" id="refreshInterval" name="refresh_interval" <?= $canManage?'':'disabled' ?>>
                <?php foreach($allowedIntervals as $minutes): ?><option value="<?= $minutes ?>" <?= (int)$settings['refresh_interval']===$minutes?'selected':'' ?>><?= $minutes ?> minute<?= $minutes>1?'s':'' ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Les nouveaux messages actualisent les compteurs et peuvent déclencher une notification système.</div>
        </div>
        <div class="p-4 border-bottom">
            <h3 class="h6 mb-1"><i class="bi bi-send me-2"></i>Envoi</h3>
            <p class="text-muted mb-3">Délai de sécurité pendant lequel un message peut encore être annulé.</p>
            <label class="form-label" for="sendDelay">Délai avant envoi</label><select class="form-select" id="sendDelay" name="send_delay" <?= $canManage?'':'disabled' ?>><?php foreach($allowedSendDelays as $seconds):?><option value="<?=$seconds?>" <?= (int)$settings['send_delay']===$seconds?'selected':'' ?>><?=$seconds?> seconde<?=$seconds>1?'s':''?></option><?php endforeach;?></select><div class="form-text">La boîte d’envoi s’actualise automatiquement une seconde après ce délai.</div>
        </div>
        <div class="p-4 border-bottom">
            <h3 class="h6 mb-1"><i class="bi bi-reply me-2"></i>Réponses</h3>
            <p class="text-muted mb-3">Comportement du rédacteur lors d’une réponse à un message.</p>
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="quoteReply" name="quote_reply" value="1" <?= !empty($settings['quote_reply'])?'checked':'' ?> <?= $canManage?'':'disabled' ?>>
                <label class="form-check-label" for="quoteReply">Citer le message d’origine lors d’une réponse</label>
            </div>
            <label class="form-label" for="signaturePosition">Emplacement de la signature</label>
            <select class="form-select" id="signaturePosition" name="signature_position" <?= $canManage?'':'disabled' ?>>
                <option value="before_quote" <?= $settings['signature_position']==='before_quote'?'selected':'' ?>>Avant le message cité</option>
                <option value="after_quote" <?= $settings['signature_position']==='after_quote'?'selected':'' ?>>Après le message cité</option>
            </select>
        </div>
        <?php if($canManage): ?><div class="p-3 d-flex justify-content-end"><button class="btn btn-danger" type="submit"><i class="bi bi-check-lg"></i> Enregistrer</button></div><?php endif; ?>
    </form>
</div>
<div class="table-card mt-4">
    <div class="p-4 border-bottom d-flex align-items-center gap-3"><div><h2 class="h5 mb-1"><i class="bi bi-airplane me-2"></i>Absence et réponses automatiques</h2><p class="text-muted mb-0">Les listes, robots et messages automatiques sont exclus.</p></div><?php if($canManage&&$messageAccounts):?><button class="btn btn-danger ms-auto" data-bs-toggle="modal" data-bs-target="#autoRule0"><i class="bi bi-plus-lg"></i> Ajouter</button><?php endif;?></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Règle</th><th>Période</th><th>Comptes mails</th><th>Statut</th><th></th></tr></thead><tbody>
    <?php if(!$autoRules):?><tr><td colspan="5" class="text-center text-muted py-4">Aucune réponse automatique enregistrée.</td></tr><?php endif;?>
    <?php foreach($autoRules as $rule):$scopeIds=$autoScope[(int)$rule['id']]??[];$scopeNames=[];foreach($messageAccounts as $account)if(in_array((int)$account['id'],$scopeIds,true))$scopeNames[]=$account['display_name']?:$account['email_address'];?>
        <tr><td><strong><?=e($rule['name'])?></strong><small class="d-block text-muted"><?=e($rule['subject'])?></small></td><td><?php if($rule['starts_at']||$rule['ends_at']):?><?=!empty($rule['starts_at'])?'Du '.e(sodium_format_date($rule['starts_at'],'d/m/Y H:i','date inconnue')):'Dès maintenant'?> <?=!empty($rule['ends_at'])?' au '.e(sodium_format_date($rule['ends_at'],'d/m/Y H:i','date inconnue')):' sans date de fin'?><?php else:?>Sans limite<?php endif;?></td><td><?php if($rule['applies_all']):?><span class="badge text-bg-primary"><i class="bi bi-infinity"></i> Tous</span><?php else:?><?=e(implode(' · ',$scopeNames)?:'Aucun')?><?php endif;?></td><td><span class="badge text-bg-<?=$rule['enabled']?'success':'secondary'?>"><?=$rule['enabled']?'Active':'Inactive'?></span></td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#autoRule<?=(int)$rule['id']?>">Modifier</button><form method="post" onsubmit="return confirm('Supprimer cette règle ?')"><input type="hidden" name="action" value="auto_reply"><input type="hidden" name="rule_action" value="delete"><input type="hidden" name="id" value="<?=(int)$rule['id']?>"><button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form></div></td></tr>
    <?php endforeach;?></tbody></table></div>
</div>
<?php if($canManage):foreach(array_merge([$blankRule],$autoRules) as $rule):$scopeIds=$autoScope[(int)$rule['id']]??[];?>
<div class="modal fade" id="autoRule<?=(int)$rule['id']?>" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><form method="post" class="auto-reply-scope-form"><input type="hidden" name="action" value="auto_reply"><input type="hidden" name="rule_action" value="save"><input type="hidden" name="id" value="<?=(int)$rule['id']?>"><div class="modal-header"><h2 class="modal-title h5"><?=$rule['id']?'Modifier la réponse automatique':'Ajouter une réponse automatique'?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-md-8"><label class="form-label">Nom de la règle</label><input class="form-control" name="name" value="<?=e($rule['name'])?>" placeholder="Ex. Congés d’été" required></div><div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="enabled" value="1" <?=$rule['enabled']?'checked':''?>><label class="form-check-label">Règle active</label></div></div>
<div class="col-md-6"><label class="form-label">Début</label><input class="form-control" type="datetime-local" name="starts_at" value="<?=!empty($rule['starts_at'])?e(sodium_format_date($rule['starts_at'],'Y-m-d\\TH:i')):''?>"></div><div class="col-md-6"><label class="form-label">Fin</label><input class="form-control" type="datetime-local" name="ends_at" value="<?=!empty($rule['ends_at'])?e(sodium_format_date($rule['ends_at'],'Y-m-d\\TH:i')):''?>"></div>
<div class="col-12"><label class="form-label">Comptes mails concernés</label><label class="form-check border rounded p-3 mb-2"><input class="form-check-input auto-reply-all" type="checkbox" name="applies_all" value="1" <?=$rule['applies_all']?'checked':''?>><span class="form-check-label ms-2"><strong>Tous les comptes</strong><small class="d-block text-muted">La règle s’appliquera aussi aux comptes accessibles ajoutés ultérieurement.</small></span></label><div class="row g-2 auto-reply-accounts"><?php foreach($messageAccounts as $account):?><div class="col-md-6"><label class="form-check border rounded p-2 h-100"><input class="form-check-input" type="checkbox" name="mail_accounts[<?=(int)$account['id']?>]" value="1" <?=in_array((int)$account['id'],$scopeIds,true)?'checked':''?> <?=$rule['applies_all']?'disabled':''?>><span class="form-check-label ms-2"><?=e($account['display_name']?:$account['email_address'])?><small class="d-block text-muted"><?=e($account['email_address'])?></small></span></label></div><?php endforeach;?></div></div>
<div class="col-12"><label class="form-label">Objet</label><input class="form-control" name="auto_subject" value="<?=e($rule['subject'])?>"></div><div class="col-12"><label class="form-label">Message</label><div class="rich-editor"><div class="rich-editor-toolbar"><button type="button" data-command="bold"><i class="bi bi-type-bold"></i></button><button type="button" data-command="italic"><i class="bi bi-type-italic"></i></button><button type="button" data-command="underline"><i class="bi bi-type-underline"></i></button><button type="button" data-command="insertUnorderedList"><i class="bi bi-list-ul"></i></button><button type="button" data-command="createLink"><i class="bi bi-link-45deg"></i></button><button type="button" data-command="removeFormat"><i class="bi bi-eraser"></i></button></div><div class="rich-editor-content" contenteditable="true"></div><textarea class="d-none" name="auto_content_html"><?=e($rule['content_html'])?></textarea></div></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Enregistrer</button></div></form></div></div></div>
<?php endforeach;endif;?>
<script>document.querySelectorAll('.auto-reply-scope-form').forEach(form=>{const all=form.querySelector('.auto-reply-all');const accounts=[...form.querySelectorAll('.auto-reply-accounts input')];const sync=()=>accounts.forEach(input=>input.disabled=all.checked);all?.addEventListener('change',sync);sync();});</script>
<?php sodium_render_footer(); ?>
