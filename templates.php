<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
sodium_require_aptitude('sodium_templates_view');
require_once __DIR__ . '/includes/layout.php';

$user=current_user();
$canOwn=sodium_can_manage_own('sodium_templates');
$canAll=sodium_can_manage_all('sodium_templates');
$accounts=sodium_accessible_mail_accounts();
$accountIds=array_map('intval',array_column($accounts,'id'));
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canOwn&&!$canAll){http_response_code(403);exit('Gestion non autorisée.');}
    $id=(int)($_POST['id']??0);
    $accountId=(int)($_POST['mail_account_id']??0);
    if($accountId&&!in_array($accountId,$accountIds,true)){http_response_code(403);exit('Compte mail non autorisé.');}
    $name=mb_substr(trim((string)($_POST['name']??'')),0,190);
    $subject=mb_substr(trim((string)($_POST['subject']??'')),0,998);
    $content=trim((string)($_POST['content_html']??''));
    $shared=!empty($_POST['is_shared'])?1:0;
    if($name===''||$content===''){flash('danger','Le nom et le contenu sont obligatoires.');redirect('/templates.php');}
    $existing=null;
    if($id){
        $stmt=$pdo->prepare('SELECT * FROM sodium_reply_templates WHERE id=?');
        $stmt->execute([$id]);$existing=$stmt->fetch();
        if(!$existing||(!$canAll&&(int)$existing['user_id']!==(int)$user['id'])){http_response_code(403);exit('Modèle en lecture seule.');}
        $pdo->prepare('UPDATE sodium_reply_templates SET mail_account_id=?,name=?,subject=?,content_html=?,is_shared=? WHERE id=?')
            ->execute([$accountId?:null,$name,$subject,$content,$shared,$id]);
        flash('success','Modèle modifié.');
    }else{
        $pdo->prepare('INSERT INTO sodium_reply_templates(user_id,mail_account_id,name,subject,content_html,is_shared) VALUES(?,?,?,?,?,?)')
            ->execute([(int)$user['id'],$accountId?:null,$name,$subject,$content,$shared]);
        flash('success','Modèle ajouté.');
    }
    redirect('/templates.php');
}
$params=$accountIds;
$scope=$accountIds?'(t.mail_account_id IS NULL OR t.mail_account_id IN ('.implode(',',array_fill(0,count($accountIds),'?')).'))':'t.mail_account_id IS NULL';
$visibility=$canAll?'1=1':'(t.user_id=? OR t.is_shared=1)';
if(!$canAll)$params[]=(int)$user['id'];
$stmt=$pdo->prepare("SELECT t.*,a.email_address,u.username,u.first_name,u.last_name FROM sodium_reply_templates t LEFT JOIN sodium_mail_accounts a ON a.id=t.mail_account_id INNER JOIN users u ON u.id=t.user_id WHERE $scope AND $visibility ORDER BY t.name");
$stmt->execute($params);$templates=$stmt->fetchAll();
$blank=['id'=>0,'user_id'=>(int)$user['id'],'mail_account_id'=>0,'name'=>'','subject'=>'','content_html'=>'','is_shared'=>0];
sodium_render_header('Modèles de réponses');
?>
<div class="d-flex justify-content-end mb-3"><?php if($canOwn||$canAll): ?><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#template0"><i class="bi bi-plus-lg"></i> Ajouter</button><?php endif; ?></div>
<div class="table-card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nom</th><th>Objet proposé</th><th>Compte mail</th><th>Portée</th><th>Propriétaire</th><th></th></tr></thead><tbody>
<?php if(!$templates):?><tr><td colspan="7" class="text-center text-muted py-4">Aucun modèle de réponse.</td></tr><?php endif;?>
<?php foreach($templates as $template):?><tr><td><strong><?=e($template['name'])?></strong></td><td><?=e($template['subject']?:'—')?></td><td><?=e($template['email_address']?:'Tous les comptes accessibles')?></td><td><span class="badge text-bg-<?=$template['is_shared']?'primary':'secondary'?>"><?=$template['is_shared']?'Partagé':'Personnel'?></span></td><td><?=e(trim($template['first_name'].' '.$template['last_name'])?:$template['username'])?></td><td class="text-end"><?php if($canAll||($canOwn&&(int)$template['user_id']===(int)$user['id'])):?><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#template<?=(int)$template['id']?>">Modifier</button><?php else:?><span class="badge text-bg-light">Lecture seule</span><?php endif;?></td></tr><?php endforeach;?>
</tbody></table></div></div>
<?php foreach(array_merge([$blank],$templates) as $template):$editable=!$template['id']||$canAll||($canOwn&&(int)$template['user_id']===(int)$user['id']);if(!$editable)continue;?>
<div class="modal fade" id="template<?=(int)$template['id']?>" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post"><input type="hidden" name="id" value="<?=(int)$template['id']?>"><div class="modal-header"><h2 class="modal-title h5"><?=$template['id']?'Modifier le modèle':'Ajouter un modèle'?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Nom</label><input class="form-control" name="name" value="<?=e($template['name'])?>" required></div><div class="col-md-6"><label class="form-label">Compte mail</label><select class="form-select" name="mail_account_id"><option value="0">Tous les comptes accessibles</option><?php foreach($accounts as $account):?><option value="<?=(int)$account['id']?>" <?=(int)$template['mail_account_id']===(int)$account['id']?'selected':''?>><?=e($account['email_address'])?></option><?php endforeach;?></select></div><div class="col-12"><label class="form-label">Objet proposé</label><input class="form-control" name="subject" value="<?=e($template['subject'])?>"></div><div class="col-12"><label class="form-label">Contenu</label><div class="rich-editor"><div class="rich-editor-toolbar"><button type="button" data-command="bold"><i class="bi bi-type-bold"></i></button><button type="button" data-command="italic"><i class="bi bi-type-italic"></i></button><button type="button" data-command="underline"><i class="bi bi-type-underline"></i></button><button type="button" data-command="insertUnorderedList"><i class="bi bi-list-ul"></i></button><button type="button" data-command="createLink"><i class="bi bi-link-45deg"></i></button><button type="button" data-command="removeFormat"><i class="bi bi-eraser"></i></button></div><div class="rich-editor-content" contenteditable="true"></div><textarea class="d-none" name="content_html"><?=e($template['content_html'])?></textarea></div></div><div class="col-12"><label class="form-check border rounded p-3"><input class="form-check-input" type="checkbox" name="is_shared" value="1" <?=$template['is_shared']?'checked':''?>><span class="form-check-label ms-2"><strong>Partager avec les utilisateurs du compte</strong></span></label></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Enregistrer</button></div></form></div></div></div>
<?php endforeach;?>
<?php sodium_render_footer();?>
