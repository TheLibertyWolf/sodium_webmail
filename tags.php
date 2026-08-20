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
    $tagId=(int)($_POST['tag_id']??0);
    $existingTag=null;
    if($tagId>0){
        $tagStmt=$pdo->prepare('SELECT t.* FROM sodium_tags t WHERE t.id=? AND EXISTS (SELECT 1 FROM sodium_tag_accounts ta WHERE ta.tag_id=t.id AND ta.mail_account_id IN ('.implode(',',array_fill(0,count($accountIds),'?')).') LIMIT 1)');
        $tagStmt->execute(array_merge([$tagId],$accountIds));$existingTag=$tagStmt->fetch();
        if(!$existingTag||(!$canManageAll&&(int)$existingTag['created_by']!==(int)$user['id'])){
            http_response_code(403);exit('Ce tag est disponible en lecture seule.');
        }
    }
    if($action==='delete'){
        if(!$existingTag){flash('danger','Tag introuvable.');redirect('/tags.php');}
        $pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM sodium_message_tags WHERE tag_id=? AND mail_account_id IN ('.implode(',',array_fill(0,count($accountIds),'?')).')')->execute(array_merge([$tagId],$accountIds));
            $pdo->prepare('DELETE FROM sodium_tag_accounts WHERE tag_id=? AND mail_account_id IN ('.implode(',',array_fill(0,count($accountIds),'?')).')')->execute(array_merge([$tagId],$accountIds));
            $remainingStmt=$pdo->prepare('SELECT COUNT(*) FROM sodium_tag_accounts WHERE tag_id=?');$remainingStmt->execute([$tagId]);
            if(!(int)$remainingStmt->fetchColumn()){
                $pdo->prepare('DELETE FROM sodium_tags WHERE id=?')->execute([$tagId]);
            }
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
    $duplicateNameStmt=$pdo->prepare('SELECT id FROM sodium_tags WHERE name=? AND id<>? LIMIT 1');$duplicateNameStmt->execute([$name,$tagId]);
    if($duplicateNameStmt->fetchColumn()){flash('warning','Un tag portant ce nom existe déjà. Modifiez sa portée plutôt que de le recréer.');redirect('/tags.php');}
    $sharedKey=(string)($existingTag['shared_key']??bin2hex(random_bytes(16)));

    $pdo->beginTransaction();
    try{
        if(!$existingTag){
            $pdo->prepare('INSERT INTO sodium_tags (mail_account_id,name,color,created_by,shared_key,applies_all,is_shared) VALUES (NULL,?,?,?,?,?,?)')
                ->execute([$name,$color,(int)$user['id'],$sharedKey,$appliesAll?1:0,$isShared?1:0]);
            $tagId=(int)$pdo->lastInsertId();
        }else{
            $pdo->prepare('UPDATE sodium_tags SET name=?,color=?,applies_all=?,is_shared=? WHERE id=?')->execute([$name,$color,$appliesAll?1:0,$isShared?1:0,$tagId]);
        }
        $existingStmt=$pdo->prepare('SELECT mail_account_id FROM sodium_tag_accounts WHERE tag_id=?');$existingStmt->execute([$tagId]);
        $existingScopes=array_map('intval',$existingStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach($targetIds as $accountId){
            $pdo->prepare('INSERT IGNORE INTO sodium_tag_accounts (tag_id,mail_account_id) VALUES (?,?)')->execute([$tagId,$accountId]);
        }
        foreach($existingScopes as $accountId){
            if(!in_array($accountId,$accountIds,true)||in_array($accountId,$targetIds,true))continue;
            $pdo->prepare('DELETE FROM sodium_message_tags WHERE tag_id=? AND mail_account_id=?')->execute([$tagId,$accountId]);
            $pdo->prepare('DELETE FROM sodium_tag_accounts WHERE tag_id=? AND mail_account_id=?')->execute([$tagId,$accountId]);
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
    $visibility=$canManageAll?'1=1':'(t.created_by=? OR t.is_shared=1)';
    $stmt=$pdo->prepare("SELECT t.id,t.shared_key,t.name,t.color,t.applies_all,t.is_shared,t.created_by,
        GROUP_CONCAT(DISTINCT ta.mail_account_id ORDER BY ta.mail_account_id) account_ids,
        GROUP_CONCAT(DISTINCT COALESCE(NULLIF(a.display_name,''),a.email_address) ORDER BY a.display_name,a.email_address SEPARATOR ' · ') account_names
        FROM sodium_tags t
        INNER JOIN sodium_tag_accounts ta ON ta.tag_id=t.id
        INNER JOIN sodium_mail_accounts a ON a.id=ta.mail_account_id
        WHERE ta.mail_account_id IN ($placeholders) AND $visibility
        GROUP BY t.id,t.shared_key,t.name,t.color,t.applies_all,t.is_shared,t.created_by ORDER BY t.name");
    $params=$accountIds;if(!$canManageAll)$params[]=(int)$user['id'];
    $stmt->execute($params);$templates=$stmt->fetchAll();
}
$blank=['id'=>0,'shared_key'=>'','name'=>'','color'=>'#6c757d','applies_all'=>0,'is_shared'=>0,'created_by'=>(int)$user['id'],'account_ids'=>''];
sodium_render_header('Tags');
?>
<div class="d-flex justify-content-end mb-3"><?php if($accounts&&$canCreate): ?><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#tagNew"><i class="bi bi-plus-lg"></i> Ajouter</button><?php endif; ?></div>
<div class="table-card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nom du tag</th><th>Portée</th><th>Comptes mails</th></tr></thead><tbody>
<?php if(!$templates): ?><tr><td colspan="3" class="text-center text-muted py-4">Aucun tag disponible.</td></tr><?php endif; ?>
<?php foreach($templates as $template): $canEditTag=$canManageAll||($canManageOwn&&(int)$template['created_by']===(int)$user['id']); ?><tr class="tag-table-row"><td><span class="mail-tag tag-table-name" style="--tag-color:<?= e($template['color']) ?>"><?= e($template['name']) ?></span></td><td><span class="badge text-bg-<?= $template['is_shared']?'primary':'secondary' ?>"><?= $template['is_shared']?'Partagé':'Personnel' ?></span></td><td><div class="tag-table-account-cell"><div class="tag-table-accounts"><?php if($template['applies_all']): ?><span class="badge rounded-pill text-bg-primary"><i class="bi bi-infinity me-1"></i>Tous</span><?php else: ?><i class="bi bi-envelope-at text-muted"></i><span><?= e($template['account_names']) ?></span><?php endif; ?></div><?php if($canEditTag): ?><div class="tag-table-actions"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tag<?= (int)$template['id'] ?>" title="Modifier"><i class="bi bi-pencil"></i><span>Modifier</span></button><form method="post" onsubmit="return confirm('Supprimer ce tag des comptes accessibles ?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="tag_id" value="<?= (int)$template['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i><span class="visually-hidden">Supprimer</span></button></form></div><?php else: ?><span class="badge text-bg-light"><i class="bi bi-eye"></i> Lecture seule</span><?php endif; ?></div></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php if($canCreate): foreach(array_merge([$blank],$templates) as $template): $isNew=(int)$template['id']===0;$canEditTag=$isNew||$canManageAll||($canManageOwn&&(int)$template['created_by']===(int)$user['id']);if(!$canEditTag)continue;$modalId=$isNew?'tagNew':'tag'.(int)$template['id'];$selected=array_map('intval',array_filter(explode(',',(string)$template['account_ids']))); ?>
<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post" class="tag-scope-form"><input type="hidden" name="tag_id" value="<?= (int)$template['id'] ?>"><div class="modal-header"><h2 class="modal-title h5"><?= $isNew?'Ajouter un tag':'Modifier le tag' ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
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
