<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
sodium_require_aptitude('sodium_full_access');
require_once __DIR__ . '/../includes/layout.php';

$canManage = sodium_can('sodium_full_access');

function sodium_person_label(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Utilisateur');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        exit('Gestion des aptitudes Sodium non autorisée.');
    }
    $action=(string)($_POST['action']??'aptitudes');
    if($action==='save_user'){
        $userId=(int)($_POST['user_id']??0);$username=trim((string)($_POST['username']??''));$first=trim((string)($_POST['first_name']??''));$last=trim((string)($_POST['last_name']??''));$email=trim((string)($_POST['email']??''));$professional=trim((string)($_POST['professional_email']??''));$status=in_array($_POST['account_status']??'active',['active','inactive','disabled'],true)?(string)$_POST['account_status']:'active';$password=(string)($_POST['password']??'');$twofaRequired=!empty($_POST['twofa_required'])?1:0;$personalLimit=!empty($_POST['sodium_personal_account_unlimited'])?4294967295:max(0,min(100,(int)($_POST['sodium_personal_account_limit']??0)));$excludedDomains=mb_substr(trim((string)($_POST['sodium_personal_excluded_domains']??'')),0,5000);$excludedAddresses=mb_substr(trim((string)($_POST['sodium_personal_excluded_addresses']??'')),0,10000);
        if(!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL)) {flash('danger','Identifiant ou adresse mail invalide.');redirect('/admin/users.php');}
        $duplicate=$pdo->prepare('SELECT COUNT(*) FROM users WHERE (LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?)) AND id<>?');$duplicate->execute([$username,$email,$userId]);if($duplicate->fetchColumn()){flash('danger','Cet identifiant ou cette adresse mail existe déjà.');redirect('/admin/users.php');}
        if(!$userId&&strlen($password)<12){flash('danger','Un mot de passe de 12 caractères minimum est obligatoire.');redirect('/admin/users.php');}
        if($userId){$params=[$username,$first,$last,$email,$professional?:null,$status,$status==='active'?1:0,$twofaRequired,$personalLimit,$excludedDomains?:null,$excludedAddresses?:null];$sql='UPDATE users SET username=?,first_name=?,last_name=?,email=?,professional_email=?,account_status=?,is_active=?,twofa_required=?,sodium_personal_account_limit=?,sodium_personal_excluded_domains=?,sodium_personal_excluded_addresses=?';if($password!==''){if(strlen($password)<12){flash('danger','Le mot de passe doit contenir au moins 12 caractères.');redirect('/admin/users.php');}$sql.=',password_hash=?';$params[]=password_hash($password,PASSWORD_DEFAULT);}$sql.=',updated_at=NOW() WHERE id=?';$params[]=$userId;$pdo->prepare($sql)->execute($params);if($status!=='active')clear_remember_cookie($userId,true);flash('success','Utilisateur modifié.');}
        else{$pdo->prepare("INSERT INTO users(username,password_hash,first_name,last_name,email,professional_email,role,account_status,is_active,theme,twofa_required,sodium_personal_account_limit,sodium_personal_excluded_domains,sodium_personal_excluded_addresses) VALUES(?,?,?,?,?,?,'user',?,?,?,?,?,?,?)")->execute([$username,password_hash($password,PASSWORD_DEFAULT),$first,$last,$email,$professional?:null,$status,$status==='active'?1:0,'dark',$twofaRequired,$personalLimit,$excludedDomains?:null,$excludedAddresses?:null]);$newId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT IGNORE INTO sodium_user_aptitudes(user_id,label) VALUES(?,'sodium_accounts_view')")->execute([$newId]);flash('success','Utilisateur créé.');}
        redirect('/admin/users.php');
    }
    if($action==='delete_user'){$userId=(int)($_POST['user_id']??0);if($userId===1){flash('danger','L’administrateur principal ne peut pas être supprimé.');redirect('/admin/users.php');}$pdo->beginTransaction();try{foreach(['sodium_user_mail_accounts','sodium_user_aptitudes','sodium_user_settings','user_sessions'] as $table)$pdo->prepare("DELETE FROM `$table` WHERE user_id=?")->execute([$userId]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);$pdo->commit();flash('success','Utilisateur supprimé.');}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('danger','Cet utilisateur possède encore des données liées et ne peut pas être supprimé.');}redirect('/admin/users.php');}
    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        flash('danger', 'Utilisateur introuvable.');
        redirect('/admin/users.php');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM sodium_user_aptitudes WHERE user_id = ?')->execute([$userId]);
        $requestedAptitudes=(array)($_POST['aptitudes']??[]);
        if($userId===1)$requestedAptitudes['sodium_full_access']=1;
        foreach (array_keys(SODIUM_APTITUDES) as $label) {
            if (!empty($requestedAptitudes[$label])) {
                $pdo->prepare('INSERT INTO sodium_user_aptitudes (user_id, label) VALUES (?, ?)')->execute([$userId, $label]);
            }
        }

        $pdo->prepare('DELETE FROM sodium_user_mail_accounts WHERE user_id = ?')->execute([$userId]);
        $selectedAccountIds = array_values(array_filter(array_map('intval', array_keys($_POST['mail_accounts'] ?? []))));
        foreach ($selectedAccountIds as $position => $accountId) {
            $accountId = (int) $accountId;
            if (!$accountId) continue;
            $pdo->prepare('INSERT INTO sodium_user_mail_accounts
                (user_id, mail_account_id, can_read, can_send, can_manage, is_default)
                VALUES (?, ?, 1, 1, 0, ?)')->execute([$userId, $accountId, $position === 0 ? 1 : 0]);
        }
        $pdo->commit();
        flash('success', 'Aptitudes et comptes mails enregistrés.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', 'Enregistrement impossible.');
    }
    redirect('/admin/users.php');
}

$users = $pdo->query('SELECT * FROM users ORDER BY last_name, first_name, username')->fetchAll();
$mailAccounts = $pdo->query('SELECT * FROM sodium_mail_accounts ORDER BY display_name, email_address')->fetchAll();
$aptitudeStmt = $pdo->prepare('SELECT label FROM sodium_user_aptitudes WHERE user_id = ?');
$accountAccessStmt = $pdo->prepare('SELECT * FROM sodium_user_mail_accounts WHERE user_id = ?');

sodium_render_header('Utilisateurs');
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#userEditNew"><i class="bi bi-person-plus me-2"></i>Nouvel utilisateur</button></div>
<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Utilisateur</th><th>Email</th><th>Email professionnel</th><th>Statut</th><th>Comptes mails</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $listedUser): ?>
                <?php
                $accountAccessStmt->execute([(int) $listedUser['id']]);
                $assignedCount = count($accountAccessStmt->fetchAll());
                ?>
                <tr>
                    <td><strong><?= e(sodium_person_label($listedUser)) ?></strong><small class="d-block text-muted"><span><?= e($listedUser['username']) ?></span></small></td>
                    <td><?= e($listedUser['email'] ?? '') ?></td>
                    <td><?= e($listedUser['professional_email'] ?? '') ?></td>
                    <td><span class="badge text-bg-<?= ($listedUser['account_status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($listedUser['account_status'] ?? 'active')) ?></span></td>
                    <td><span class="badge text-bg-light"><?= $assignedCount ?></span></td>
                    <td class="text-end"><?php if ($canManage): ?><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sodiumUser<?= (int) $listedUser['id'] ?>">Aptitudes</button><button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#userEdit<?= (int)$listedUser['id'] ?>"><i class="bi bi-pencil"></i></button><?php if((int)$listedUser['id']!==1): ?><form method="post" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=(int)$listedUser['id']?>"><button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form><?php endif; ?></div><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $editableUsers=array_merge([['id'=>0,'username'=>'','first_name'=>'','last_name'=>'','email'=>'','professional_email'=>'','account_status'=>'active','twofa_required'=>0,'sodium_personal_account_limit'=>0,'sodium_personal_excluded_domains'=>'','sodium_personal_excluded_addresses'=>'']],$users);foreach($editableUsers as $editable):$editId=(int)$editable['id'];?>
<div class="modal fade" id="userEdit<?=$editId?:'New'?>" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-lg"><form class="modal-content" method="post"><div class="modal-header"><h2 class="modal-title h5"><?=$editId?'Modifier l’utilisateur':'Nouvel utilisateur'?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="save_user"><input type="hidden" name="user_id" value="<?=$editId?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Identifiant</label><input class="form-control" name="username" value="<?=e($editable['username'])?>" required></div><div class="col-md-6"><label class="form-label">Statut</label><select class="form-select" name="account_status" <?=$editId===1?'disabled':''?>><?php foreach(['active'=>'Actif','inactive'=>'Inactif','disabled'=>'Désactivé'] as $value=>$label):?><option value="<?=$value?>" <?=$editable['account_status']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select><?php if($editId===1):?><input type="hidden" name="account_status" value="active"><?php endif;?></div><div class="col-md-6"><label class="form-label">Prénom</label><input class="form-control" name="first_name" value="<?=e($editable['first_name'])?>"></div><div class="col-md-6"><label class="form-label">Nom</label><input class="form-control" name="last_name" value="<?=e($editable['last_name'])?>"></div><div class="col-md-6"><label class="form-label">Adresse mail</label><input class="form-control" type="email" name="email" value="<?=e($editable['email'])?>" required></div><div class="col-md-6"><label class="form-label">Adresse mail professionnelle</label><input class="form-control" type="email" name="professional_email" value="<?=e($editable['professional_email'])?>"></div><div class="col-12"><label class="form-label"><?=$editId?'Nouveau mot de passe (laisser vide pour conserver)':'Mot de passe'?></label><input class="form-control" type="password" name="password" minlength="12" <?=$editId?'':'required'?>></div><div class="col-12"><label class="border rounded p-3 d-flex align-items-start gap-3"><input class="form-check-input flex-shrink-0 m-0 mt-1" type="checkbox" name="twofa_required" value="1" <?=!empty($editable['twofa_required'])?'checked':''?>><span><strong>Imposer l’authentification à deux facteurs</strong><small class="d-block text-muted">L’utilisateur devra configurer le 2FA dans son profil et ne pourra plus le désactiver tant que cette obligation reste active.</small></span></label></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger" type="submit">Enregistrer</button></div></form></div></div>
<?php endforeach;?>

<?php foreach ($users as $managedUser): ?>
    <?php
    $aptitudeStmt->execute([(int) $managedUser['id']]);
    $userAptitudes = array_column($aptitudeStmt->fetchAll(), 'label');
    $isProtectedAdministrator=(int)$managedUser['id']===1;
    $hasFullAccess = $isProtectedAdministrator||in_array('sodium_full_access', $userAptitudes, true);
    $accountAccessStmt->execute([(int) $managedUser['id']]);
    $accessMap = [];
    foreach ($accountAccessStmt->fetchAll() as $access) $accessMap[(int) $access['mail_account_id']] = $access;
    ?>
    <div class="modal fade" id="sodiumUser<?= (int) $managedUser['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><form method="post" class="sodium-aptitudes-form">
            <input type="hidden" name="user_id" value="<?= (int) $managedUser['id'] ?>">
            <div class="modal-header"><h2 class="modal-title h5">Aptitudes Sodium — <?= e(sodium_person_label($managedUser)) ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <h3 class="h6 mb-3">Aptitudes globales</h3>
                <div class="aptitude-full-card mb-3">
                    <label class="form-check mb-0"><input class="form-check-input full-access-toggle" type="checkbox" name="aptitudes[sodium_full_access]" value="1" <?= $hasFullAccess?'checked':'' ?> <?= $isProtectedAdministrator?'disabled':'' ?>><span class="form-check-label"><span class="aptitude-card-icon"><i class="bi bi-shield-check"></i></span><span><strong>Accès complet</strong><small><?= $isProtectedAdministrator?'Accès permanent de l’administrateur principal — cette aptitude ne peut pas être retirée.':'Donne accès à toutes les fonctions de Sodium, à tous les comptes mails autorisés et à l’administration des utilisateurs.' ?></small></span></span></label>
                </div>
                <div class="alert alert-info py-2 mb-3"><strong>Consultation</strong> autorise l’affichage et l’utilisation des éléments existants. <strong>Gestion</strong> permet aussi de les créer, modifier et supprimer. Pour les éléments personnels, la <strong>gestion personnelle</strong> ne concerne que ceux créés par l’utilisateur ; la <strong>gestion complète</strong> couvre tous les éléments accessibles.</div>
                <div class="row g-3 mb-4 aptitude-module-grid">
                    <div class="col-md-6"><div class="aptitude-module-card"><div class="aptitude-module-heading"><span class="aptitude-card-icon"><i class="bi bi-key"></i></span><strong>Licence</strong></div><div class="aptitude-module-rights"><label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="aptitudes[licence]" value="1" <?= in_array('licence',$userAptitudes,true)?'checked':'' ?>><span class="form-check-label">Voir et gérer</span></label></div></div></div>
                    <?php foreach ([
                        'Comptes mails'=>['bi-envelope-at','sodium_accounts_view','sodium_accounts_manage',''],
                        'Messages'=>['bi-envelope','sodium_settings_view','sodium_settings_manage',''],
                    ] as $module=>$definition): ?>
                    <div class="col-md-6"><div class="aptitude-module-card <?= $hasFullAccess?'is-disabled':'' ?>"><div class="aptitude-module-heading"><span class="aptitude-card-icon"><i class="bi <?= e($definition[0]) ?>"></i></span><strong><?= e($module) ?></strong></div><div class="aptitude-module-rights"><label class="form-check mb-0" title="Afficher et utiliser les éléments existants"><input class="form-check-input module-aptitude" type="checkbox" name="aptitudes[<?= e($definition[1]) ?>]" value="1" <?= in_array($definition[1],$userAptitudes,true)?'checked':'' ?> <?= $hasFullAccess?'disabled':'' ?>><span class="form-check-label">Consultation</span></label><label class="form-check mb-0" title="Créer, modifier et supprimer"><input class="form-check-input module-aptitude" type="checkbox" name="aptitudes[<?= e($definition[2]) ?>]" value="1" <?= in_array($definition[2],$userAptitudes,true)?'checked':'' ?> <?= $hasFullAccess?'disabled':'' ?>><span class="form-check-label">Gestion</span></label></div></div></div>
                    <?php endforeach; ?>
                    <?php foreach ([
                        'Signatures'=>['bi-person-vcard','sodium_signatures_view','sodium_signatures_manage_my','sodium_signatures_manage_full'],
                        'Tags'=>['bi-tags','sodium_labels_view','sodium_labels_manage_my','sodium_labels_manage_full'],
                        'Modèles de réponses'=>['bi-chat-square-text','sodium_templates_view','sodium_templates_manage_my','sodium_templates_manage_full'],
                    ] as $module=>$definition): ?>
                    <div class="col-md-6"><div class="aptitude-module-card <?= $hasFullAccess?'is-disabled':'' ?>"><div class="aptitude-module-heading"><span class="aptitude-card-icon"><i class="bi <?= e($definition[0]) ?>"></i></span><strong><?= e($module) ?></strong></div><div class="aptitude-module-rights flex-wrap"><label class="form-check mb-0" title="Afficher et utiliser les éléments accessibles"><input class="form-check-input module-aptitude" type="checkbox" name="aptitudes[<?= e($definition[1]) ?>]" value="1" <?= in_array($definition[1],$userAptitudes,true)?'checked':'' ?> <?= $hasFullAccess?'disabled':'' ?>><span class="form-check-label">Consultation</span></label><label class="form-check mb-0" title="Gérer uniquement ses propres éléments"><input class="form-check-input module-aptitude" type="checkbox" name="aptitudes[<?= e($definition[2]) ?>]" value="1" <?= in_array($definition[2],$userAptitudes,true)?'checked':'' ?> <?= $hasFullAccess?'disabled':'' ?>><span class="form-check-label">Gestion personnelle</span></label><label class="form-check mb-0" title="Gérer tous les éléments accessibles"><input class="form-check-input module-aptitude" type="checkbox" name="aptitudes[<?= e($definition[3]) ?>]" value="1" <?= in_array($definition[3],$userAptitudes,true)?'checked':'' ?> <?= $hasFullAccess?'disabled':'' ?>><span class="form-check-label">Gestion complète</span></label></div></div></div>
                    <?php endforeach; ?>
                </div>

                <h3 class="h6 mb-3">Comptes mails autorisés</h3>
                <?php if (!$mailAccounts): ?>
                    <div class="alert alert-info mb-0">Créez d’abord un compte dans « Comptes mails ».</div>
                <?php else: ?>
                    <div class="row g-3"><?php foreach ($mailAccounts as $mailAccount): $access=$accessMap[(int)$mailAccount['id']]??[]; ?><div class="col-md-6"><label class="mail-access-card"><input class="form-check-input" type="checkbox" name="mail_accounts[<?= (int)$mailAccount['id'] ?>]" value="1" <?= !empty($access['can_read'])?'checked':'' ?>><span class="mail-access-visual"><?php if(!empty($mailAccount['icon_path'])): ?><img class="mail-account-image large" style="--account-color:<?= e($mailAccount['label_color']) ?>" src="<?= e($mailAccount['icon_path']) ?>" alt=""><?php else: ?><span class="mail-account-avatar large" style="--account-color:<?= e($mailAccount['label_color']) ?>"><?= e(strtoupper(substr((string)$mailAccount['email_address'],0,1))) ?></span><?php endif; ?></span><span class="mail-access-copy"><strong><?= e($mailAccount['display_name']?:$mailAccount['email_address']) ?></strong><small><?= e($mailAccount['email_address']) ?></small></span></label></div><?php endforeach; ?></div>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger" type="submit">Enregistrer</button></div>
        </form></div></div>
    </div>
<?php endforeach; ?>
<script>
const personalAccountRules=<?=json_encode(array_column($editableUsers,null,'id'),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
document.querySelectorAll('[id^="userEdit"] form').forEach(form=>{const id=Number(form.querySelector('[name="user_id"]')?.value||0);const rules=personalAccountRules[id]||{};const row=form.querySelector('.modal-body .row');if(!row)return;const wrapper=document.createElement('div');wrapper.className='col-12';wrapper.innerHTML=`<hr><h3 class="h6">Ajout de comptes mails personnels</h3><div class="row g-3"><div class="col-md-4"><label class="form-label">Nombre maximal</label><input class="form-control" type="number" min="0" max="100" name="sodium_personal_account_limit" value="${Number(rules.sodium_personal_account_limit||0)}"><div class="form-text">0 interdit tout ajout.</div></div><div class="col-md-8"><label class="form-label">Domaines interdits</label><textarea class="form-control" name="sodium_personal_excluded_domains" rows="2" placeholder="example.com, domaine.fr"></textarea></div><div class="col-12"><label class="form-label">Adresses mails interdites</label><textarea class="form-control" name="sodium_personal_excluded_addresses" rows="2" placeholder="adresse@example.com, autre@domaine.fr"></textarea><div class="form-text">Valeurs séparées par des virgules.</div></div></div>`;wrapper.querySelector('[name="sodium_personal_excluded_domains"]').value=rules.sodium_personal_excluded_domains||'';wrapper.querySelector('[name="sodium_personal_excluded_addresses"]').value=rules.sodium_personal_excluded_addresses||'';row.appendChild(wrapper);});
document.querySelectorAll('[id^="userEdit"] form').forEach(form=>{const id=Number(form.querySelector('[name="user_id"]')?.value||0),rules=personalAccountRules[id]||{},limit=Number(rules.sodium_personal_account_limit||0),number=form.querySelector('[name="sodium_personal_account_limit"]'),domains=form.querySelector('[name="sodium_personal_excluded_domains"]');if(!number||!domains)return;number.value=limit>=4294967295?'0':String(limit);number.closest('.col-md-4')?.classList.replace('col-md-4','col-md-6');number.closest('div').querySelector('label').textContent='Nombre d’adresses mails supplémentaires';domains.closest('.col-md-8')?.classList.replace('col-md-8','col-12');const unlimited=document.createElement('div');unlimited.className='col-md-6 d-flex align-items-end';unlimited.innerHTML=`<label class="border rounded p-3 w-100"><input class="form-check-input me-2" type="checkbox" name="sodium_personal_account_unlimited" value="1" ${limit>=4294967295?'checked':''}><strong>Illimité</strong></label>`;number.closest('.col-md-6')?.after(unlimited);const heading=number.closest('.col-12')?.querySelector('h3');if(heading)heading.textContent='Adresses mails supplémentaires';});
document.querySelectorAll('[id^="userEdit"] form').forEach(form=>{const number=form.querySelector('[name="sodium_personal_account_limit"]'),unlimited=form.querySelector('[name="sodium_personal_account_unlimited"]');if(!number||!unlimited)return;const sync=()=>{number.disabled=unlimited.checked;number.classList.toggle('bg-body-secondary',unlimited.checked);};unlimited.addEventListener('change',sync);sync();});
document.querySelectorAll('.sodium-aptitudes-form').forEach(form=>{
    const full=form.querySelector('.full-access-toggle');
    const modules=form.querySelectorAll('.module-aptitude');
    const cards=form.querySelectorAll('.aptitude-module-card');
    const sync=()=>{
        modules.forEach(input=>{input.disabled=full.checked;if(full.checked)input.checked=false;});
        cards.forEach(card=>card.classList.toggle('is-disabled',full.checked));
    };
    full?.addEventListener('change',sync);
    sync();
});
</script>
<?php sodium_render_footer(); ?>
