<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
sodium_require_aptitude('sodium_labels_view');
require_once __DIR__ . '/includes/layout.php';

$user=current_user();
$canManageOwn=sodium_can_manage_own('sodium_labels');
$canManageAll=sodium_can_manage_all('sodium_labels');
$canCreate=$canManageOwn||$canManageAll;
$accounts=sodium_accessible_mail_accounts();
$accountIds=array_map('intval',array_column($accounts,'id'));
$tagColors=[
    '#0d6efd'=>'Primary','#6c757d'=>'Secondary','#198754'=>'Success','#dc3545'=>'Danger',
    '#ffc107'=>'Warning','#0dcaf0'=>'Info','#f8f9fa'=>'Light','#212529'=>'Dark',
    '#6610f2'=>'Indigo','#6f42c1'=>'Violet','#d63384'=>'Rose','#fd7e14'=>'Orange',
    '#20c997'=>'Turquoise','#084298'=>'Bleu nuit','#0f5132'=>'Vert forêt','#842029'=>'Bordeaux',
    '#664d03'=>'Ocre','#055160'=>'Pétrole','#495057'=>'Ardoise','#ffffff'=>'Blanc',
];

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canCreate){http_response_code(403);exit('Gestion des tags non autorisée.');}
    if(!$accountIds){flash('danger','Aucun compte mail accessible.');redirect('/tags.php');}
    $action=(string)($_POST['action']??'save');
    $sharedKey=preg_match('/^[a-f0-9]{32}$/',(string)($_POST['shared_key']??''))?(string)$_POST['shared_key']:'';
    $existingTemplate=null;
    if($sharedKey!==''){
        $templateStmt=$pdo->prepare('SELECT * FROM sodium_tag_templates WHERE shared_key=?');
        $templateStmt->execute([$sharedKey]);$existingTemplate=$templateStmt->fetch();
        if(!$existingTemplate||(!$canManageAll&&(int)$existingTemplate['created_by']!==(int)$user['id'])){
            http_response_code(403);exit('Ce tag est disponible en lecture seule.');
        }
    }
    if($action==='delete'){
        if($sharedKey===''){flash('danger','Tag introuvable.');redirect('/tags.php');}
        $pdo->beginTransaction();
        try{
            $tagIdsStmt=$pdo->prepare('SELECT id FROM sodium_tags WHERE shared_key=? AND mail_account_id IN ('.implode(',',array_fill(0,count($accountIds),'?')).')');$tagIdsStmt->execute(array_merge([$sharedKey],$accountIds));$tagIds=array_map('intval',$tagIdsStmt->fetchAll(PDO::FETCH_COLUMN));
            if($tagIds){$pdo->prepare('DELETE FROM sodium_message_tags WHERE tag_id IN ('.implode(',',array_fill(0,count($tagIds),'?')).')')->execute($tagIds);}
            if($tagIds)$pdo->prepare('DELETE FROM sodium_tags WHERE id IN ('.implode(',',array_fill(0,count($tagIds),'?')).')')->execute($tagIds);
            $remainingStmt=$pdo->prepare('SELECT COUNT(*) FROM sodium_tags WHERE shared_key=?');$remainingStmt->execute([$sharedKey]);
            if(!(int)$remainingStmt->fetchColumn())$pdo->prepare('DELETE FROM sodium_tag_templates WHERE shared_key=?')->execute([$sharedKey]);
            $pdo->commit();flash('success','Tag supprimé des comptes mails accessibles.');
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();flash('danger','Suppression impossible.');}
        redirect('/tags.php');
    }

    $name=mb_substr(trim((string)($_POST['name']??'')),0,80);
    $color=array_key_exists((string)($_POST['color']??''),$tagColors)?(string)$_POST['color']:'#6c757d';
    $appliesAll=!empty($_POST['applies_all']);
    $isShared=!empty($_POST['is_shared']);
    $selectedIds=array_values(array_intersect($accountIds,array_map('intval',array_keys($_POST['accounts']??[]))));
    $targetIds=$appliesAll?$accountIds:$selectedIds;
    if($name===''||!$targetIds){flash('danger','Renseignez le nom et sélectionnez au moins un compte.');redirect('/tags.php');}
    if($sharedKey==='')$sharedKey=bin2hex(random_bytes(16));

    $pdo->beginTransaction();
    try{
        $pdo->prepare("INSERT INTO sodium_tag_templates (shared_key,name,color,applies_all,is_shared,created_by) VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE name=VALUES(name),color=VALUES(color),applies_all=VALUES(applies_all),is_shared=VALUES(is_shared),updated_at=NOW()")
            ->execute([$sharedKey,$name,$color,$appliesAll?1:0,$isShared?1:0,(int)$user['id']]);
        $pdo->prepare('UPDATE sodium_tags SET name=?,color=?,applies_all=?,is_shared=? WHERE shared_key=? AND mail_account_id IN ('.implode(',',array_fill(0,count($accountIds),'?')).')')
            ->execute(array_merge([$name,$color,$appliesAll?1:0,$isShared?1:0,$sharedKey],$accountIds));

        $existingStmt=$pdo->prepare('SELECT id,mail_account_id FROM sodium_tags WHERE shared_key=?');$existingStmt->execute([$sharedKey]);
        $existingRows=$existingStmt->fetchAll();$existingByAccount=[];
        foreach($existingRows as $row)$existingByAccount[(int)$row['mail_account_id']]=(int)$row['id'];
        foreach($targetIds as $accountId){
            if(isset($existingByAccount[$accountId]))continue;
            $pdo->prepare('INSERT INTO sodium_tags (mail_account_id,name,color,created_by,shared_key,applies_all,is_shared) VALUES (?,?,?,?,?,?,?)')
                ->execute([$accountId,$name,$color,(int)($existingTemplate['created_by']??$user['id']),$sharedKey,$appliesAll?1:0,$isShared?1:0]);
        }
        foreach($existingByAccount as $accountId=>$tagId){
            if(!in_array($accountId,$accountIds,true)||in_array($accountId,$targetIds,true))continue;
            $pdo->prepare('DELETE FROM sodium_message_tags WHERE tag_id=?')->execute([$tagId]);
            $pdo->prepare('DELETE FROM sodium_tags WHERE id=?')->execute([$tagId]);
        }
        $pdo->commit();flash('success','Tag et comptes associés enregistrés.');
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        flash('danger','Enregistrement impossible : '.$exception->getMessage());
    }
    redirect('/tags.php');
}

$templates=[];
if($accountIds){
    $placeholders=implode(',',array_fill(0,count($accountIds),'?'));
    $visibility=$canManageAll?'1=1':'(tt.created_by=? OR tt.is_shared=1)';
    $stmt=$pdo->prepare("SELECT tt.shared_key,tt.name,tt.color,tt.applies_all,tt.is_shared,tt.created_by,
        GROUP_CONCAT(DISTINCT t.id ORDER BY t.id SEPARATOR ',') object_ids,
        GROUP_CONCAT(DISTINCT t.mail_account_id ORDER BY t.mail_account_id) account_ids,
        GROUP_CONCAT(DISTINCT COALESCE(NULLIF(a.display_name,''),a.email_address) ORDER BY a.display_name,a.email_address SEPARATOR ' · ') account_names
        FROM sodium_tag_templates tt
        INNER JOIN sodium_tags t ON t.shared_key=tt.shared_key
        INNER JOIN sodium_mail_accounts a ON a.id=t.mail_account_id
        WHERE t.mail_account_id IN ($placeholders) AND $visibility
        GROUP BY tt.shared_key,tt.name,tt.color,tt.applies_all,tt.is_shared,tt.created_by ORDER BY tt.name");
    $params=$accountIds;if(!$canManageAll)$params[]=(int)$user['id'];
    $stmt->execute($params);$templates=$stmt->fetchAll();
}
$blank=['shared_key'=>'','name'=>'','color'=>'#6c757d','applies_all'=>0,'is_shared'=>0,'created_by'=>(int)$user['id'],'account_ids'=>''];
sodium_render_header('Tags');
?>
<div class="d-flex justify-content-end mb-3"><?php if($accounts&&$canCreate): ?><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#tagNew"><i class="bi bi-plus-lg"></i> Ajouter</button><?php endif; ?></div>
<div class="table-card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nom du tag</th><th>Portée</th><th>Comptes mails</th></tr></thead><tbody>
<?php if(!$templates): ?><tr><td colspan="3" class="text-center text-muted py-4">Aucun tag disponible.</td></tr><?php endif; ?>
<?php foreach($templates as $template): $canEditTag=$canManageAll||($canManageOwn&&(int)$template['created_by']===(int)$user['id']); ?><tr class="tag-table-row"><td><span class="mail-tag tag-table-name" style="--tag-color:<?= e($template['color']) ?>"><?= e($template['name']) ?></span></td><td><span class="badge text-bg-<?= $template['is_shared']?'primary':'secondary' ?>"><?= $template['is_shared']?'Partagé':'Personnel' ?></span></td><td><div class="tag-table-account-cell"><div class="tag-table-accounts"><?php if($template['applies_all']): ?><span class="badge rounded-pill text-bg-primary"><i class="bi bi-infinity me-1"></i>Tous</span><?php else: ?><i class="bi bi-envelope-at text-muted"></i><span><?= e($template['account_names']) ?></span><?php endif; ?></div><?php if($canEditTag): ?><div class="tag-table-actions"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tag<?= e($template['shared_key']) ?>" title="Modifier"><i class="bi bi-pencil"></i><span>Modifier</span></button><form method="post" onsubmit="return confirm('Supprimer ce tag des comptes accessibles ?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="shared_key" value="<?= e($template['shared_key']) ?>"><button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i><span class="visually-hidden">Supprimer</span></button></form></div><?php else: ?><span class="badge text-bg-light"><i class="bi bi-eye"></i> Lecture seule</span><?php endif; ?></div></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php if($canCreate): foreach(array_merge([$blank],$templates) as $template): $isNew=$template['shared_key']==='';$canEditTag=$isNew||$canManageAll||($canManageOwn&&(int)$template['created_by']===(int)$user['id']);if(!$canEditTag)continue;$modalId=$isNew?'tagNew':'tag'.$template['shared_key'];$selected=array_map('intval',array_filter(explode(',',(string)$template['account_ids']))); ?>
<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post" class="tag-scope-form"><input type="hidden" name="shared_key" value="<?= e($template['shared_key']) ?>"><div class="modal-header"><h2 class="modal-title h5"><?= $isNew?'Ajouter un tag':'Modifier le tag' ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-12"><label class="form-label">Nom</label><input class="form-control" name="name" value="<?= e($template['name']) ?>" required></div>
<div class="col-12"><label class="form-label">Couleur</label><div class="account-color-palette"><?php foreach($tagColors as $color=>$colorName): ?><label class="account-color-choice" title="<?= e($colorName) ?>"><input type="radio" name="color" value="<?= e($color) ?>" <?= strtolower((string)$template['color'])===strtolower($color)?'checked':'' ?> required><span style="--swatch-color:<?= e($color) ?>"></span></label><?php endforeach; ?></div></div>
<div class="col-12"><label class="form-check border rounded p-3 tag-all-choice"><input class="form-check-input tag-all-toggle" type="checkbox" name="applies_all" value="1" <?= $template['applies_all']?'checked':'' ?>><span class="form-check-label ms-2"><strong>Tous les comptes</strong><small class="d-block text-muted">Le tag sera aussi ajouté automatiquement aux comptes mails créés ultérieurement.</small></span></label></div>
<div class="col-12"><label class="form-check border rounded p-3"><input class="form-check-input" type="checkbox" name="is_shared" value="1" <?= $template['is_shared']?'checked':'' ?>><span class="form-check-label ms-2"><strong>Partager sur les comptes mails</strong><small class="d-block text-muted">Les autres utilisateurs autorisés pourront voir et utiliser ce tag, sans pouvoir le modifier.</small></span></label></div>
<div class="col-12"><div class="module-title text-body-secondary mb-2">Comptes sélectionnés</div><div class="row g-2 tag-account-grid"><?php foreach($accounts as $account): ?><div class="col-md-6"><label class="form-check border rounded p-3 h-100"><input class="form-check-input tag-account-check" type="checkbox" name="accounts[<?= (int)$account['id'] ?>]" value="1" <?= in_array((int)$account['id'],$selected,true)||$template['applies_all']?'checked':'' ?> <?= $template['applies_all']?'disabled':'' ?>><span class="form-check-label ms-2"><strong><?= e($account['display_name']?:$account['email_address']) ?></strong><small class="d-block text-muted"><?= e($account['email_address']) ?></small></span></label></div><?php endforeach; ?></div></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Enregistrer</button></div></form></div></div></div>
<?php endforeach; ?>
<?php endif; ?>
<script>document.querySelectorAll('.tag-scope-form').forEach(form=>{const all=form.querySelector('.tag-all-toggle');const checks=form.querySelectorAll('.tag-account-check');all?.addEventListener('change',()=>checks.forEach(check=>{check.disabled=all.checked;if(all.checked)check.checked=true;}));});</script>
<?php sodium_render_footer(); ?>
