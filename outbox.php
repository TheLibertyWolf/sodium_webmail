<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$accounts = sodium_accessible_mail_accounts();
$outboxSettings=sodium_user_settings((int)current_user()['id']);
$outboxReloadDelay=max(2,min(61,(int)$outboxSettings['send_delay']+1));
$accountIds = array_map('intval', array_column($accounts, 'id'));
$messages = [];
$statusFilter=in_array($_GET['status']??'',['draft','scheduled','failed'],true)?(string)$_GET['status']:'all';
if ($accountIds) {
    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare("SELECT m.*,a.email_address,a.display_name,a.icon_path,a.label_text,a.label_color
        FROM sodium_composed_messages m
        INNER JOIN sodium_mail_accounts a ON a.id=m.mail_account_id
        WHERE m.user_id=? AND m.status IN ('draft','scheduled','failed') AND m.mail_account_id IN ($placeholders) ".($statusFilter!=='all'?'AND m.status='.$pdo->quote($statusFilter):'')."
        ORDER BY FIELD(m.status,'failed','draft','scheduled'),COALESCE(m.scheduled_at,m.updated_at) DESC,m.id DESC");
    $stmt->execute(array_merge([(int) current_user()['id']], $accountIds));
    $messages = $stmt->fetchAll();
}

sodium_render_header('Boîte d’envoi');
?>
<div class="table-card">
    <div class="p-3 border-bottom"><div class="btn-group btn-group-sm"><?php foreach(['all'=>'Tous','draft'=>'Brouillons','scheduled'=>'Programmés','failed'=>'Échecs'] as $key=>$label):?><a class="btn btn-outline-secondary <?=$statusFilter===$key?'active':''?>" href="/outbox.php<?=$key!=='all'?'?status='.$key:''?>"><?=$label?></a><?php endforeach;?></div></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 outbox-table">
            <colgroup><col class="outbox-col-status"><col class="outbox-col-date"><col class="outbox-col-sender"><col class="outbox-col-recipients"><col class="outbox-col-subject"><col class="outbox-col-priority"><col class="outbox-col-actions"></colgroup>
            <thead><tr><th>Statut</th><th>Date</th><th>Expéditeur</th><th>Destinataires</th><th>Objet</th><th>Priorité</th><th></th></tr></thead>
            <tbody>
            <?php if (!$messages): ?><tr><td colspan="7"><div class="mail-empty-content compact"><i class="bi bi-send-check"></i><h2>Boîte d’envoi vide</h2><p>Les brouillons, envois programmés et échecs apparaîtront ici.</p></div></td></tr><?php endif; ?>
            <?php foreach ($messages as $message): $recipients=json_decode((string)$message['to_json'],true)?:[]; ?>
                <tr class="outbox-message-row" data-edit-message="<?= (int)$message['id'] ?>" tabindex="0" title="Cliquer pour modifier ce message">
                    <td><?php if($message['status']==='draft'):?><span class="badge text-bg-secondary">Brouillon</span><?php elseif($message['status']==='failed'):?><span class="badge text-bg-danger" title="<?=e($message['last_error']??'')?>">Échec</span><?php else:?><span class="badge text-bg-primary">Programmé</span><?php endif;?></td>
                    <td class="outbox-date"><strong><?= e(sodium_format_date($message['scheduled_at']?:$message['updated_at'],'d/m/Y à H:i','Date inconnue')) ?></strong><small class="d-block text-muted"><?= e(sodium_human_date(sodium_parse_timestamp($message['scheduled_at']?:$message['updated_at']))) ?></small></td>
                    <td><div class="global-account-identity outbox-sender"><?php if(!empty($message['icon_path'])):?><img class="mail-account-image" style="--account-color:<?=e($message['label_color'])?>" src="<?=e($message['icon_path'])?>" alt=""><?php else:?><span class="mail-account-avatar" style="--account-color:<?=e($message['label_color'])?>"><?=e(strtoupper(substr((string)$message['email_address'],0,1)))?></span><?php endif;?><?php if(!empty($message['label_text'])):?><span class="mailbox-label" style="--label-color:<?=e($message['label_color'])?>;--label-text-color:<?=e(sodium_color_contrast((string)$message['label_color']))?>"><?=e($message['label_text'])?></span><?php endif;?><span class="outbox-sender-copy"><span><?= e($message['display_name']?:$message['email_address']) ?></span><small class="text-muted"><?= e($message['email_address']) ?></small></span></div></td>
                    <td><div class="outbox-ellipsis" title="<?= e(implode(' · ',$recipients)) ?>"><?= e(implode(' · ',$recipients)) ?></div></td>
                    <td><div class="outbox-ellipsis" title="<?= e(trim((string)$message['subject'])?:'(pas d’objet)') ?>"><strong><?= e(trim((string)$message['subject'])?:'(pas d’objet)') ?></strong></div></td>
                    <td><?php if($message['priority']==='high'): ?><span class="badge text-bg-danger">Haute</span><?php elseif($message['priority']==='low'): ?><span class="badge text-bg-secondary">Basse</span><?php else: ?><span class="badge text-bg-light">Normale</span><?php endif; ?></td>
                    <td class="text-end"><div class="outbox-actions"><?php if($message['status']==='scheduled'&&!empty($message['undo_until'])&&strtotime((string)$message['undo_until'])>=time()):?><form method="post" action="/composed-action.php"><input type="hidden" name="id" value="<?=(int)$message['id']?>"><input type="hidden" name="action" value="undo"><button class="btn btn-sm btn-warning text-nowrap"><i class="bi bi-arrow-counterclockwise me-1"></i><span>Annuler</span></button></form><?php endif;?><?php if(in_array($message['status'],['draft','failed'],true)):?><a class="btn btn-sm btn-outline-secondary text-nowrap" href="/outbox.php?compose_draft=<?=(int)$message['id']?>"><i class="bi bi-pencil me-1"></i>Modifier</a><?php endif;?><form method="post" action="/composed-action.php" onsubmit="return confirm('Supprimer ce message ?')"><input type="hidden" name="id" value="<?=(int)$message['id']?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger" title="Supprimer" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.querySelectorAll('[data-edit-message]').forEach(row=>{
    const edit=()=>{
        const form=document.createElement('form');form.method='post';form.action='/composed-action.php';form.hidden=true;
        const values={id:row.dataset.editMessage,action:'edit',_csrf:document.querySelector('meta[name="csrf-token"]')?.content||''};
        Object.entries(values).forEach(([name,value])=>{const input=document.createElement('input');input.name=name;input.value=value;form.appendChild(input);});
        document.body.appendChild(form);form.submit();
    };
    row.addEventListener('click',event=>{if(event.target.closest('a,button,form,input,select'))return;edit();});
    row.addEventListener('keydown',event=>{if((event.key==='Enter'||event.key===' ')&&!event.target.closest('a,button,form,input,select')){event.preventDefault();edit();}});
});
const refreshOutboxFrame=async()=>{if(document.querySelector('.modal.show'))return;try{const response=await fetch(location.href,{headers:{'X-Sodium-Partial':'outbox'}});if(!response.ok)return;const documentCopy=new DOMParser().parseFromString(await response.text(),'text/html');const next=documentCopy.querySelector('.table-card .table-responsive');const current=document.querySelector('.table-card .table-responsive');if(next&&current){current.replaceWith(next);next.querySelectorAll('[data-edit-message]').forEach(row=>row.addEventListener('click',event=>{if(event.target.closest('a,button,form,input,select'))return;const form=document.createElement('form');form.method='post';form.action='/composed-action.php';form.innerHTML=`<input name="id" value="${row.dataset.editMessage}"><input name="action" value="edit"><input name="_csrf" value="${document.querySelector('meta[name=csrf-token]')?.content||''}">`;document.body.appendChild(form);form.submit();}));}}catch(error){}};
window.setInterval(refreshOutboxFrame,<?= $outboxReloadDelay*1000 ?>);
</script>
<?php sodium_render_footer(); ?>
