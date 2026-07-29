<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
sodium_require_aptitude('sodium_signatures_view');
require_once __DIR__ . '/includes/layout.php';

$user = current_user();
$canManageOwn = sodium_can_manage_own('sodium_signatures');
$canManageAll = sodium_can_manage_all('sodium_signatures');
$canCreate = $canManageOwn || $canManageAll;
$accounts = sodium_accessible_mail_accounts();
$accountIds = array_map('intval', array_column($accounts, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canCreate) {
        http_response_code(403);
        exit('Gestion des signatures non autorisée.');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $accountId = (int) ($_POST['mail_account_id'] ?? 0);
    if (!in_array($accountId, $accountIds, true)) {
        flash('danger', 'Compte mail non autorisé.');
        redirect('/signatures.php');
    }
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 190);
    $senderName = mb_substr(trim((string) ($_POST['sender_name'] ?? '')), 0, 190);
    $content = trim((string) ($_POST['content_html'] ?? ''));
    $isDefault = !empty($_POST['is_default']) ? 1 : 0;
    $isShared = !empty($_POST['is_shared']) ? 1 : 0;
    if ($name === '' || $senderName === '' || $content === '') {
        flash('danger', 'Le nom, le nom d’expéditeur et le contenu de la signature sont obligatoires.');
        redirect('/signatures.php');
    }
    $existing = null;
    if ($id) {
        $existingStmt = $pdo->prepare('SELECT * FROM sodium_signatures WHERE id=? AND mail_account_id IN (' . implode(',', array_fill(0, count($accountIds), '?')) . ')');
        $existingStmt->execute(array_merge([$id], $accountIds));
        $existing = $existingStmt->fetch();
        if (!$existing || (!$canManageAll && (int)$existing['user_id'] !== (int)$user['id'])) {
            http_response_code(403);
            exit('Cette signature est disponible en lecture seule.');
        }
    }
    if ($isDefault) {
        $ownerId = $existing ? (int)$existing['user_id'] : (int)$user['id'];
        $pdo->prepare('UPDATE sodium_signatures SET is_default=0 WHERE user_id=? AND mail_account_id=?')->execute([$ownerId, $accountId]);
    }
    if ($id) {
        $stmt = $pdo->prepare('UPDATE sodium_signatures SET mail_account_id=?, name=?, sender_name=?, content_html=?, is_default=?, is_shared=? WHERE id=?');
        $stmt->execute([$accountId, $name, $senderName, $content, $isDefault, $isShared, $id]);
        flash('success', 'Signature modifiée.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO sodium_signatures (user_id, mail_account_id, name, sender_name, content_html, is_default, is_shared) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int) $user['id'], $accountId, $name, $senderName, $content, $isDefault, $isShared]);
        flash('success', 'Signature ajoutée.');
    }
    redirect('/signatures.php');
}

$signatures = [];
if ($accountIds) {
    $visibility = $canManageAll ? '1=1' : '(s.user_id=? OR s.is_shared=1)';
    $stmt = $pdo->prepare('SELECT s.*, a.email_address, a.display_name, u.first_name owner_first_name, u.last_name owner_last_name, u.username owner_username
        FROM sodium_signatures s INNER JOIN sodium_mail_accounts a ON a.id=s.mail_account_id
        INNER JOIN users u ON u.id=s.user_id
        WHERE s.mail_account_id IN (' . implode(',', array_fill(0, count($accountIds), '?')) . ') AND ' . $visibility . '
        ORDER BY s.is_default DESC, s.name');
    $params = $accountIds;
    if (!$canManageAll) $params[] = (int)$user['id'];
    $stmt->execute($params);
    $signatures = $stmt->fetchAll();
}
$blank = ['id'=>'', 'user_id'=>(int)$user['id'], 'mail_account_id'=>$accounts[0]['id'] ?? 0, 'name'=>'', 'sender_name'=>'', 'content_html'=>'', 'is_default'=>0, 'is_shared'=>0];

sodium_render_header('Signatures');
?>
<div class="d-flex justify-content-end mb-3">
    <?php if ($accounts && $canCreate): ?><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#signatureNew"><i class="bi bi-plus-lg"></i> Ajouter</button><?php endif; ?>
</div>
<div class="table-card">
    <?php if (!$accounts): ?>
        <div class="mail-empty-content compact"><i class="bi bi-envelope-x"></i><h2>Aucun compte mail</h2><p>Une boîte mail doit vous être attribuée avant de créer une signature.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Signature</th><th>Propriétaire</th><th>Portée</th><th>Nom expéditeur</th><th>Compte mail</th><th>Par défaut</th><th>Contenu</th><th></th></tr></thead>
            <tbody>
            <?php if (!$signatures): ?><tr><td colspan="8" class="text-center text-muted py-4">Aucune signature enregistrée.</td></tr><?php endif; ?>
            <?php foreach ($signatures as $signature): ?>
                <tr>
                    <td><strong><?= e($signature['name']) ?></strong></td>
                    <td><?= e(trim(($signature['owner_first_name'] ?? '').' '.($signature['owner_last_name'] ?? '')) ?: $signature['owner_username']) ?></td>
                    <td><span class="badge text-bg-<?= $signature['is_shared']?'primary':'secondary' ?>"><?= $signature['is_shared']?'Partagée':'Personnelle' ?></span></td>
                    <td><?= e($signature['sender_name']) ?></td>
                    <td><?= e($signature['display_name'] ?: $signature['email_address']) ?><small class="d-block text-muted"><?= e($signature['email_address']) ?></small></td>
                    <td><?= $signature['is_default'] ? '<span class="badge text-bg-success">Oui</span>' : '—' ?></td>
                    <td class="signature-excerpt"><?= e(mb_strimwidth(strip_tags($signature['content_html']), 0, 90, '…')) ?></td>
                    <td class="text-end"><?php $canEditSignature=$canManageAll||($canManageOwn&&(int)$signature['user_id']===(int)$user['id']); if($canEditSignature): ?><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#signature<?= (int) $signature['id'] ?>">Modifier</button><?php else: ?><span class="badge text-bg-light"><i class="bi bi-eye"></i> Lecture seule</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php if($canCreate): foreach (array_merge([$blank], $signatures) as $signature): $isNew=empty($signature['id']); $canEditSignature=$isNew||$canManageAll||($canManageOwn&&(int)$signature['user_id']===(int)$user['id']); if(!$canEditSignature)continue; $modalId=$isNew?'signatureNew':'signature'.(int)$signature['id']; ?>
<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post">
        <input type="hidden" name="id" value="<?= e($signature['id']) ?>">
        <div class="modal-header"><h2 class="modal-title h5"><?= $isNew ? 'Ajouter une signature' : 'Modifier la signature' ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nom de la signature</label><input class="form-control" name="name" value="<?= e($signature['name']) ?>" placeholder="Ex. Commerciale" required></div>
            <div class="col-md-6"><label class="form-label">Nom expéditeur</label><input class="form-control" name="sender_name" value="<?= e($signature['sender_name']) ?>" placeholder="Prénom NOM" required><div class="form-text">Nom visible par les destinataires.</div></div>
            <div class="col-12"><label class="form-label">Compte mail</label><select class="form-select" name="mail_account_id" required><?php foreach ($accounts as $account): ?><option value="<?= (int)$account['id'] ?>" <?= (int)$signature['mail_account_id']===(int)$account['id']?'selected':'' ?>><?= e($account['display_name'] ?: $account['email_address']) ?> — <?= e($account['email_address']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">Signature</label><div class="rich-editor identity-editor"><div class="rich-editor-toolbar"><button type="button" data-command="undo" title="Annuler"><i class="bi bi-arrow-counterclockwise"></i></button><button type="button" data-command="redo" title="Rétablir"><i class="bi bi-arrow-clockwise"></i></button><span></span><button type="button" data-command="formatBlock" data-value="p">P</button><button type="button" data-command="formatBlock" data-value="h2">H</button><button type="button" data-command="bold"><i class="bi bi-type-bold"></i></button><button type="button" data-command="italic"><i class="bi bi-type-italic"></i></button><button type="button" data-command="underline"><i class="bi bi-type-underline"></i></button><button type="button" data-command="strikeThrough"><i class="bi bi-type-strikethrough"></i></button><span></span><button type="button" data-command="justifyLeft"><i class="bi bi-text-left"></i></button><button type="button" data-command="justifyCenter"><i class="bi bi-text-center"></i></button><button type="button" data-command="justifyRight"><i class="bi bi-text-right"></i></button><button type="button" data-command="insertUnorderedList"><i class="bi bi-list-ul"></i></button><button type="button" data-command="insertOrderedList"><i class="bi bi-list-ol"></i></button><button type="button" data-command="createLink"><i class="bi bi-link-45deg"></i></button><button type="button" data-command="removeFormat"><i class="bi bi-eraser"></i></button><label class="editor-color" title="Couleur du texte"><input type="color" data-editor-color value="#1f2937"></label></div><div class="rich-editor-content" contenteditable="true" data-placeholder="Composez votre signature…"></div><textarea class="d-none" name="content_html"><?= e($signature['content_html']) ?></textarea></div></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" <?= $signature['is_default']?'checked':'' ?>> <span class="form-check-label">Signature par défaut pour ce compte</span></label></div>
            <div class="col-12"><label class="form-check border rounded p-3"><input class="form-check-input" type="checkbox" name="is_shared" value="1" <?= $signature['is_shared']?'checked':'' ?>><span class="form-check-label ms-2"><strong>Partager sur le compte mail</strong><small class="d-block text-muted">Les autres utilisateurs autorisés sur ce compte pourront utiliser et consulter cette signature, sans pouvoir la modifier.</small></span></label></div>
        </div></div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger" type="submit">Enregistrer</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php sodium_render_footer(); ?>
