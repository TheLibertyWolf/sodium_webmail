<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();
sodium_require_aptitude('sodium_accounts_view');
require_once __DIR__ . '/../includes/layout.php';

$canManage = sodium_can('sodium_accounts_manage');
$accountColors = [
    '#0d6efd' => 'Primary', '#6c757d' => 'Secondary', '#198754' => 'Success', '#dc3545' => 'Danger',
    '#ffc107' => 'Warning', '#0dcaf0' => 'Info', '#212529' => 'Dark', '#6f42c1' => 'Violet',
    '#6610f2' => 'Indigo', '#d63384' => 'Rose', '#fd7e14' => 'Orange', '#20c997' => 'Turquoise',
    '#ffffff' => 'Blanc',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        exit('Gestion des comptes mails non autorisée.');
    }
    if(in_array($_POST['action']??'',['delete_user_account','ban_user_account'],true)){
        $id=(int)($_POST['id']??0);$stmt=$pdo->prepare('SELECT email_address,icon_path FROM sodium_mail_accounts WHERE id=? AND is_user_managed=1');$stmt->execute([$id]);$target=$stmt->fetch();
        if($target){$pdo->beginTransaction();try{if($_POST['action']==='ban_user_account')$pdo->prepare('INSERT IGNORE INTO sodium_banned_mail_addresses(email_address,banned_by_user_id) VALUES(?,?)')->execute([strtolower((string)$target['email_address']),(int)current_user()['id']]);$pdo->prepare('DELETE FROM sodium_user_mail_accounts WHERE mail_account_id=?')->execute([$id]);$pdo->prepare('DELETE FROM sodium_mail_accounts WHERE id=? AND is_user_managed=1')->execute([$id]);$pdo->commit();if(!empty($target['icon_path'])&&str_starts_with((string)$target['icon_path'],'/uploads/mail-icons/'))@unlink(__DIR__.'/..'.(string)$target['icon_path']);flash('success',$_POST['action']==='ban_user_account'?'Compte supprimé et adresse interdite.':'Compte personnel supprimé.');}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();flash('danger','Suppression impossible.');}}
        redirect('/admin/mail-accounts.php');
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'refresh') {
        sodium_refresh_account_cache($id, true);
        flash('success', 'Compte mail synchronisé.');
        redirect('/admin/mail-accounts.php');
    }
    $email = strtolower(trim((string) ($_POST['email_address'] ?? '')));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $status = ($_POST['account_status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    $imapEncryption = in_array($_POST['imap_encryption'] ?? '', ['ssl', 'tls', 'none'], true) ? $_POST['imap_encryption'] : 'ssl';
    $smtpEncryption = in_array($_POST['smtp_encryption'] ?? '', ['ssl', 'tls', 'none'], true) ? $_POST['smtp_encryption'] : 'ssl';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Une adresse mail valide est obligatoire.');
        redirect('/admin/mail-accounts.php');
    }

    $existingIcon = null;
    if ($id) {
        $iconStmt = $pdo->prepare('SELECT icon_path FROM sodium_mail_accounts WHERE id=?');
        $iconStmt->execute([$id]);
        $existingIcon = $iconStmt->fetchColumn() ?: null;
    }
    $iconPath = $existingIcon;
    if (!empty($_FILES['icon']['tmp_name']) && is_uploaded_file($_FILES['icon']['tmp_name'])) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['icon']['tmp_name']);
        $imageInfo = @getimagesize($_FILES['icon']['tmp_name']);
        $extensions = ['image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || ($imageInfo['mime'] ?? '') !== $mime || (int) $_FILES['icon']['size'] > 2 * 1024 * 1024) {
            flash('danger', 'Icône invalide : fichier PNG ou WebP de 2 Mo maximum.');
            redirect('/admin/mail-accounts.php');
        }
        $filename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $target = __DIR__ . '/../uploads/mail-icons/' . $filename;
        if (!move_uploaded_file($_FILES['icon']['tmp_name'], $target)) {
            flash('danger', 'Enregistrement de l’icône impossible.');
            redirect('/admin/mail-accounts.php');
        }
        $iconPath = '/uploads/mail-icons/' . $filename;
    }

    $params = [
        'email_address' => $email,
        'display_name' => $displayName,
        'imap_host' => trim((string) ($_POST['imap_host'] ?? 'localhost')) ?: 'localhost',
        'imap_port' => max(1, min(65535, (int) ($_POST['imap_port'] ?? 993))),
        'imap_encryption' => $imapEncryption,
        'smtp_host' => trim((string) ($_POST['smtp_host'] ?? 'localhost')) ?: 'localhost',
        'smtp_port' => max(1, min(65535, (int) ($_POST['smtp_port'] ?? 465))),
        'smtp_encryption' => $smtpEncryption,
        'login_name' => trim((string) ($_POST['login_name'] ?? '')) ?: $email,
        'account_status' => $status,
        'label_text' => mb_substr(trim((string) ($_POST['label_text'] ?? '')), 0, 60),
        'label_color' => array_key_exists((string) ($_POST['label_color'] ?? ''), $accountColors) ? $_POST['label_color'] : '#dc3545',
        'icon_path' => $iconPath,
    ];
    $password = (string) ($_POST['password'] ?? '');
    if ($password !== '') $params['password_cipher'] = sodium_encrypt_secret($password);

    try {
        if ($id) {
            $params['id'] = $id;
            $pdo->prepare('UPDATE sodium_mail_accounts SET email_address=:email_address, display_name=:display_name,
                imap_host=:imap_host, imap_port=:imap_port, imap_encryption=:imap_encryption,
                smtp_host=:smtp_host, smtp_port=:smtp_port, smtp_encryption=:smtp_encryption,
                login_name=:login_name, account_status=:account_status, label_text=:label_text,
                label_color=:label_color, icon_path=:icon_path' . (isset($params['password_cipher']) ? ', password_cipher=:password_cipher' : '') . ' WHERE id=:id')->execute($params);
            flash('success', 'Compte mail modifié.');
        } else {
            $pdo->prepare('INSERT INTO sodium_mail_accounts
                (email_address, display_name, imap_host, imap_port, imap_encryption, smtp_host, smtp_port, smtp_encryption, login_name, account_status, label_text, label_color, icon_path, password_cipher)
                VALUES (:email_address, :display_name, :imap_host, :imap_port, :imap_encryption, :smtp_host, :smtp_port, :smtp_encryption, :login_name, :account_status, :label_text, :label_color, :icon_path, :password_cipher)')
                ->execute(array_merge($params, ['password_cipher' => $params['password_cipher'] ?? '']));
            sodium_apply_global_tags_to_account((int)$pdo->lastInsertId());
            flash('success', 'Compte mail ajouté.');
        }
    } catch (PDOException $exception) {
        flash('danger', str_contains($exception->getMessage(), 'Duplicate') ? 'Cette adresse existe déjà.' : 'Enregistrement impossible.');
    }
    redirect('/admin/mail-accounts.php');
}

$allAccounts = $pdo->query('SELECT a.*,
    (SELECT COUNT(*) FROM sodium_user_mail_accounts uma WHERE uma.mail_account_id=a.id) assigned_users
    FROM sodium_mail_accounts a ORDER BY a.display_name, a.email_address')->fetchAll();
$accounts=array_values(array_filter($allAccounts,static fn(array $account):bool=>empty($account['is_user_managed'])));
$userAccounts=array_values(array_filter($allAccounts,static fn(array $account):bool=>!empty($account['is_user_managed'])));
$blankAccount = [
    'id' => '', 'email_address' => '', 'display_name' => '', 'imap_host' => 'localhost', 'imap_port' => 993,
    'imap_encryption' => 'ssl', 'smtp_host' => 'localhost', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
    'login_name' => '', 'account_status' => 'active', 'label_text' => '', 'label_color' => '#dc3545',
    'icon_path' => null, 'unread_count' => 0, 'quota_used_kb' => null, 'quota_limit_kb' => null, 'last_error' => null,
];

sodium_render_header('Gestion Comptes mails');
?>
<div class="d-flex justify-content-end mb-3">
    <?php if ($canManage): ?><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#mailAccountNew"><i class="bi bi-plus-lg"></i> Ajouter</button><?php endif; ?>
</div>
<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Compte</th><th>Label</th><th>Quota</th><th>Non lus</th><th>Utilisateurs</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php if (!$accounts): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucun compte mail enregistré.</td></tr><?php endif; ?>
            <?php foreach ($accounts as $account): ?>
                <?php $quota = sodium_format_quota($account['quota_used_kb'] !== null ? (int) $account['quota_used_kb'] : null, $account['quota_limit_kb'] !== null ? (int) $account['quota_limit_kb'] : null); ?>
                <tr>
                    <td><span class="d-flex align-items-center gap-2"><?php if ($account['icon_path']): ?><img class="mail-account-image" style="--account-color:<?= e($account['label_color']) ?>" src="<?= e($account['icon_path']) ?>" alt=""><?php endif; ?><span><strong><?= e($account['display_name'] ?: $account['email_address']) ?></strong><small class="d-block text-muted"><?= e($account['email_address']) ?></small></span></span><?php if ($account['last_error']): ?><small class="d-block text-danger mt-1"><?= e($account['last_error']) ?></small><?php endif; ?></td>
                    <td><?php if ($account['label_text']): ?><span class="mailbox-label" style="--label-color:<?= e($account['label_color']) ?>;--label-text-color:<?= e(sodium_color_contrast((string)$account['label_color'])) ?>"><?= e($account['label_text']) ?></span><?php else: ?>—<?php endif; ?></td>
                    <td><small><?= e($quota['label']) ?></small><div class="progress mt-1" style="height:4px"><div class="progress-bar <?= $quota['percent'] >= 90 ? 'bg-danger' : '' ?>" style="width:<?= (int) $quota['percent'] ?>%"></div></div></td>
                    <td><span class="badge text-bg-danger"><?= (int) $account['unread_count'] ?></span></td>
                    <td><?= (int) $account['assigned_users'] ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge text-bg-<?= $account['account_status'] === 'active' ? 'success' : 'secondary' ?>"><?= $account['account_status'] === 'active' ? 'Actif' : 'Inactif' ?></span>
                            <?php if (empty($account['password_cipher'])): ?>
                                <span class="badge text-bg-warning"><i class="bi bi-key me-1"></i>Non configuré</span>
                            <?php elseif (!empty($account['last_error'])): ?>
                                <span class="badge text-bg-danger" title="<?= e($account['last_error']) ?>"><i class="bi bi-x-circle me-1"></i>Déconnecté</span>
                            <?php elseif (!empty($account['last_sync_at'])): ?>
                                <span class="badge text-bg-success" title="Dernière relève : <?= e(sodium_format_date($account['last_sync_at'],'d/m/Y H:i','date inconnue')) ?>"><i class="bi bi-check-circle me-1"></i>Connecté</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary"><i class="bi bi-hourglass-split me-1"></i>À vérifier</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-end"><?php if ($canManage): ?><div class="btn-group btn-group-sm"><form method="post"><input type="hidden" name="action" value="refresh"><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><button class="btn btn-outline-primary" title="Synchroniser"><i class="bi bi-arrow-repeat"></i></button></form><button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mailAccount<?= (int) $account['id'] ?>">Modifier</button></div><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="table-card mt-4"><div class="p-3 border-bottom"><h2 class="h6 mb-0">Adresses mails ajoutées par les utilisateurs autorisés</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Compte</th><th>Utilisateur</th><th>Statut</th><th></th></tr></thead><tbody><?php if(!$userAccounts):?><tr><td colspan="4" class="text-center text-muted py-4">Aucun compte ajouté par un utilisateur.</td></tr><?php endif;?><?php foreach($userAccounts as $userAccount):?><tr><td><strong><?=e($userAccount['display_name']?:$userAccount['email_address'])?></strong><small class="d-block text-muted"><?=e($userAccount['email_address'])?></small></td><td>Utilisateur <?= (int)$userAccount['created_by_user_id'] ?></td><td><span class="badge text-bg-<?=empty($userAccount['last_error'])?'success':'danger'?>"><?=empty($userAccount['last_error'])?'Connecté':'Erreur'?></span></td><td class="text-end"><div class="btn-group btn-group-sm"><form method="post" onsubmit="return confirm('Supprimer ce compte personnel ?')"><input type="hidden" name="action" value="delete_user_account"><input type="hidden" name="id" value="<?=(int)$userAccount['id']?>"><button class="btn btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form><form method="post" onsubmit="return confirm('Supprimer et interdire cette adresse ?')"><input type="hidden" name="action" value="ban_user_account"><input type="hidden" name="id" value="<?=(int)$userAccount['id']?>"><button class="btn btn-danger" title="Supprimer et bannir"><i class="bi bi-slash-circle"></i></button></form></div></td></tr><?php endforeach;?></tbody></table></div></div>
<?php foreach (array_merge([$blankAccount], $accounts) as $account): $isNew = empty($account['id']); $modalId = $isNew ? 'mailAccountNew' : 'mailAccount' . (int) $account['id']; ?>
<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post" enctype="multipart/form-data">
        <div class="modal-header"><h2 class="modal-title h5"><?= $isNew ? 'Ajouter un compte mail' : 'Modifier le compte mail' ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="id" value="<?= e($account['id']) ?>">
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label">Adresse mail</label><input class="form-control" type="email" name="email_address" value="<?= e($account['email_address']) ?>" placeholder="nom@domaine.fr" required></div>
                <div class="col-md-5"><label class="form-label">Nom affiché</label><input class="form-control" name="display_name" value="<?= e($account['display_name']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Identifiant IMAP/SMTP</label><input class="form-control" name="login_name" value="<?= e($account['login_name']) ?>" placeholder="Par défaut : adresse mail"></div>
                <div class="col-md-6"><label class="form-label">Mot de passe</label><input class="form-control" type="password" name="password" autocomplete="new-password" placeholder="<?= $isNew ? 'Obligatoire pour synchroniser' : 'Laisser vide pour conserver' ?>"></div>
                <div class="col-md-4"><label class="form-label">Texte du label</label><input class="form-control" name="label_text" value="<?= e($account['label_text']) ?>" placeholder="Direction, Support…"></div>
                <div class="col-md-8"><label class="form-label">Couleur de fond de l’icône</label><div class="account-color-palette"><?php foreach($accountColors as $color=>$colorName): ?><label class="account-color-choice" title="<?= e($colorName) ?>"><input type="radio" name="label_color" value="<?= e($color) ?>" <?= strtolower((string)$account['label_color'])===strtolower($color)?'checked':'' ?>><span style="--swatch-color:<?= e($color) ?>"></span></label><?php endforeach; ?></div></div>
                <div class="col-md-8"><label class="form-label">Icône du compte</label><input class="form-control" type="file" name="icon" accept="image/png,image/webp,.png,.webp"><div class="form-text">PNG ou WebP — 2 Mo maximum.</div></div>
                <div class="col-md-4"><label class="form-label">Statut</label><select class="form-select" name="account_status"><option value="active" <?= $account['account_status'] === 'active' ? 'selected' : '' ?>>Actif</option><option value="inactive" <?= $account['account_status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option></select></div>
                <div class="col-12"><hr><h3 class="h6">Réception IMAP</h3></div>
                <div class="col-md-6"><label class="form-label">Serveur</label><input class="form-control" name="imap_host" value="<?= e($account['imap_host']) ?>" required></div>
                <div class="col-md-3"><label class="form-label">Port</label><input class="form-control" type="number" name="imap_port" value="<?= (int) $account['imap_port'] ?>" min="1" max="65535" required></div>
                <div class="col-md-3"><label class="form-label">Sécurité</label><select class="form-select" name="imap_encryption"><?php foreach (['ssl'=>'SSL','tls'=>'TLS','none'=>'Aucune'] as $key=>$label): ?><option value="<?= $key ?>" <?= $account['imap_encryption'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><hr><h3 class="h6">Envoi SMTP</h3></div>
                <div class="col-md-6"><label class="form-label">Serveur</label><input class="form-control" name="smtp_host" value="<?= e($account['smtp_host']) ?>" required></div>
                <div class="col-md-3"><label class="form-label">Port</label><input class="form-control" type="number" name="smtp_port" value="<?= (int) $account['smtp_port'] ?>" min="1" max="65535" required></div>
                <div class="col-md-3"><label class="form-label">Sécurité</label><select class="form-select" name="smtp_encryption"><?php foreach (['ssl'=>'SSL','tls'=>'TLS','none'=>'Aucune'] as $key=>$label): ?><option value="<?= $key ?>" <?= $account['smtp_encryption'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="alert alert-info mt-3 mb-0"><i class="bi bi-shield-lock"></i> Le mot de passe est chiffré en AES-256-GCM avec une clé stockée hors du dossier web.</div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger" type="submit">Enregistrer</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php sodium_render_footer(); ?>
