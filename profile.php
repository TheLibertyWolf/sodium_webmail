<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$user = current_user();
$setupSecret = $_SESSION['twofa_setup_secret'] ?? null;
if (!$setupSecret) {
    $setupSecret = twofa_generate_secret();
    $_SESSION['twofa_setup_secret'] = $setupSecret;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'profile');

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (!password_verify($current, (string) $user['password_hash'])) {
            flash('danger', 'Mot de passe actuel incorrect.');
        } elseif (strlen($new) < 10) {
            flash('danger', 'Le nouveau mot de passe doit contenir au moins 10 caractères.');
        } elseif ($new !== $confirm) {
            flash('danger', 'La confirmation du mot de passe ne correspond pas.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?')->execute([password_hash($new, PASSWORD_BCRYPT), (int) $user['id']]);
            clear_remember_cookie((int) $user['id'], true);
            flash('success', 'Mot de passe modifié. Les connexions permanentes ont été révoquées.');
        }
        redirect('/profile.php');
    }

    if ($action === 'theme') {
        $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
        $pdo->prepare('UPDATE users SET theme=?, updated_at=NOW() WHERE id=?')->execute([$theme, (int) $user['id']]);
        flash('success', 'Thème mis à jour.');
        redirect('/profile.php');
    }

    if ($action === 'enable_2fa') {
        $secret = (string) ($_SESSION['twofa_setup_secret'] ?? '');
        $code = trim((string) ($_POST['twofa_code'] ?? ''));
        if (!$secret || !twofa_verify_code($secret, $code)) {
            flash('danger', 'Code 2FA incorrect.');
        } else {
            $pdo->prepare('UPDATE users SET twofa_secret=?, twofa_enabled=1, updated_at=NOW() WHERE id=?')->execute([$secret, (int) $user['id']]);
            unset($_SESSION['twofa_setup_secret']);
            flash('success', 'Authentification à deux facteurs activée.');
        }
        redirect('/profile.php');
    }

    if ($action === 'disable_2fa') {
        if (!empty($user['twofa_required'])) {
            flash('danger', 'La 2FA est obligatoire pour votre compte.');
        } else {
            $pdo->prepare('UPDATE users SET twofa_secret=NULL, twofa_enabled=0, updated_at=NOW() WHERE id=?')->execute([(int) $user['id']]);
            unset($_SESSION['twofa_setup_secret']);
            flash('success', 'Authentification à deux facteurs désactivée.');
        }
        redirect('/profile.php');
    }

    if ($action === 'revoke_session') {
        ensure_user_sessions_table();
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT selector FROM user_sessions WHERE id=? AND user_id=?');
        $stmt->execute([$sessionId, (int) $user['id']]);
        $selector = (string) ($stmt->fetchColumn() ?: '');
        if ($selector !== '') {
            $pdo->prepare('DELETE FROM user_sessions WHERE id=? AND user_id=?')->execute([$sessionId, (int) $user['id']]);
            if ($selector === current_remember_selector()) clear_remember_cookie();
            flash('success', 'Session révoquée.');
        }
        redirect('/profile.php');
    }

    if ($action === 'revoke_other_sessions') {
        ensure_user_sessions_table();
        $selector = current_remember_selector();
        if ($selector !== '') {
            $pdo->prepare('DELETE FROM user_sessions WHERE user_id=? AND selector<>?')->execute([(int) $user['id'], $selector]);
        } else {
            $pdo->prepare('DELETE FROM user_sessions WHERE user_id=?')->execute([(int) $user['id']]);
        }
        flash('success', 'Les autres sessions ont été révoquées.');
        redirect('/profile.php');
    }

    $data = [
        'address_line1' => trim((string) ($_POST['address_line1'] ?? '')),
        'address_line2' => trim((string) ($_POST['address_line2'] ?? '')),
        'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
        'city' => trim((string) ($_POST['city'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'id' => (int) $user['id'],
    ];
    $pdo->prepare('UPDATE users SET address_line1=:address_line1, address_line2=:address_line2,
        postal_code=:postal_code, city=:city, phone=:phone, updated_at=NOW() WHERE id=:id')->execute($data);
    flash('success', 'Profil mis à jour.');
    redirect('/profile.php');
}

$user = current_user();
$setupSecret = (string) ($_SESSION['twofa_setup_secret'] ?? twofa_generate_secret());
$_SESSION['twofa_setup_secret'] = $setupSecret;
$otpauth = twofa_otpauth_uri($user, $setupSecret);
$qrImage = '';
$barcodeFile = __DIR__ . '/vendor/tcpdf/tcpdf_barcodes_2d.php';
if (is_file($barcodeFile)) {
    require_once $barcodeFile;
    $qr = new TCPDF2DBarcode($otpauth, 'QRCODE,M');
    $qrImage = 'data:image/png;base64,' . base64_encode($qr->getBarcodePngData(8, 8, [0, 0, 0]));
}

ensure_user_sessions_table();
$sessionStmt = $pdo->prepare('SELECT * FROM user_sessions WHERE user_id=? AND expires_at>=NOW() ORDER BY last_used_at DESC');
$sessionStmt->execute([(int) $user['id']]);
$activeSessions = $sessionStmt->fetchAll();
$currentSelector = current_remember_selector();
$deviceLabel = static function (string $agent): string {
    $os = str_contains($agent, 'iPhone') ? 'iPhone' : (str_contains($agent, 'Android') ? 'Android' : (str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Macintosh') ? 'Mac' : 'Appareil')));
    $browser = str_contains($agent, 'Edg/') ? 'Edge' : (str_contains($agent, 'Firefox/') ? 'Firefox' : (str_contains($agent, 'Chrome/') ? 'Chrome' : (str_contains($agent, 'Safari/') ? 'Safari' : 'Navigateur')));
    return $os . ' · ' . $browser;
};

sodium_render_header('Profil');
?>
<div class="row justify-content-center g-4">
    <div class="col-xl-8">
        <div class="form-card">
            <h2 class="h5 mb-3">Mes informations</h2>
            <form method="post">
                <input type="hidden" name="action" value="profile">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Prénom</label><input class="form-control" value="<?= e($user['first_name'] ?? '') ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Nom</label><input class="form-control" value="<?= e($user['last_name'] ?? '') ?>" disabled></div>
                    <div class="col-md-3"><label class="form-label">Sexe</label><input class="form-control" value="<?= e($user['gender'] ?? '') ?>" disabled></div>
                    <div class="col-md-3"><label class="form-label">Date de naissance</label><input class="form-control" type="date" value="<?= e($user['birth_date'] ?? '') ?>" disabled></div>
                    <div class="col-md-3"><label class="form-label">Lieu de naissance</label><input class="form-control" value="<?= e($user['birth_place'] ?? '') ?>" disabled></div>
                    <div class="col-md-3"><label class="form-label">N° de sécurité sociale</label><input class="form-control" value="<?= e($user['social_security_number'] ?? '') ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Adresse mail</label><input class="form-control profile-locked-field" type="email" value="<?= e($user['email'] ?? '') ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Adresse mail pro</label><input class="form-control profile-locked-field" type="email" value="<?= e($user['professional_email'] ?? '') ?>" disabled></div>
                    <div class="col-12"><label class="form-label">Adresse 1</label><input class="form-control" name="address_line1" value="<?= e($user['address_line1'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label">Adresse 2</label><input class="form-control" name="address_line2" value="<?= e($user['address_line2'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">CP</label><input class="form-control" name="postal_code" value="<?= e($user['postal_code'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Ville</label><input class="form-control" name="city" value="<?= e($user['city'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Tél.</label><input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                </div>
                <button class="btn btn-danger mt-3" type="submit">Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="form-card">
            <form method="post" class="d-flex align-items-center justify-content-between gap-3">
                <input type="hidden" name="action" value="theme">
                <input type="hidden" name="theme" value="light">
                <div><h2 class="h5 mb-1">Thème</h2><div class="small text-muted">Adapter l’interface à votre préférence d’affichage.</div></div>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input profile-theme-switch" type="checkbox" role="switch" id="profileTheme" name="theme" value="dark" <?= ($user['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label ms-2" for="profileTheme">Mode sombre</label>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="form-card">
            <h2 class="h5 mb-3">Gestion de la sécurité</h2>
            <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#passwordChange"><i class="bi bi-key"></i> Changer de mot de passe</button>
            <div class="collapse" id="passwordChange">
                <form method="post" class="row g-3 mb-4">
                    <input type="hidden" name="action" value="change_password">
                    <div class="col-md-4"><label class="form-label">Mot de passe actuel</label><input class="form-control" type="password" name="current_password" required></div>
                    <div class="col-md-4"><label class="form-label">Nouveau mot de passe</label><input class="form-control" type="password" name="new_password" minlength="10" required></div>
                    <div class="col-md-4"><label class="form-label">Confirmation</label><input class="form-control" type="password" name="confirm_password" minlength="10" required></div>
                    <div class="col-12"><button class="btn btn-danger">Modifier le mot de passe</button></div>
                </form>
            </div>
            <div class="border rounded p-3">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <div><div class="fw-semibold">Authentification à deux facteurs</div><div class="text-muted small">Application d’authentification TOTP.</div></div>
                    <span class="badge <?= !empty($user['twofa_enabled']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($user['twofa_enabled']) ? '2FA activée' : '2FA inactive' ?></span>
                </div>
                <?php if (!empty($user['twofa_enabled'])): ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#twofaModal"><i class="bi bi-shield-lock"></i> Modifier</button>
                        <form method="post"><input type="hidden" name="action" value="disable_2fa"><button class="btn btn-outline-danger" <?= !empty($user['twofa_required']) ? 'disabled' : '' ?>><i class="bi bi-trash"></i> Supprimer</button></form>
                    </div>
                <?php else: ?>
                    <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#twofaModal"><i class="bi bi-shield-lock"></i> Activer la 2FA</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="form-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div><h2 class="h5 mb-1">Sessions actives</h2><div class="small text-muted">Appareils autorisés par « Se souvenir de moi ».</div></div>
                <?php if (count($activeSessions) > 1): ?><form method="post"><input type="hidden" name="action" value="revoke_other_sessions"><button class="btn btn-outline-danger btn-sm">Déconnecter les autres appareils</button></form><?php endif; ?>
            </div>
            <?php if (!$activeSessions): ?>
                <div class="alert alert-secondary mb-0">Aucune connexion persistante active.</div>
            <?php else: ?>
                <div class="vstack gap-2">
                    <?php foreach ($activeSessions as $session): $isCurrent = $currentSelector !== '' && $currentSelector === (string) $session['selector']; ?>
                        <div class="border rounded p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div><div class="fw-semibold"><i class="bi bi-phone me-1"></i><?= e($deviceLabel((string) ($session['user_agent'] ?? ''))) ?><?php if ($isCurrent): ?> <span class="badge text-bg-primary">Cet appareil</span><?php endif; ?></div><div class="small text-muted">IP <?= e($session['ip_address'] ?: 'inconnue') ?> · Dernière activité <?= e(date('d-m-Y H:i', strtotime((string) $session['last_used_at']))) ?></div></div>
                            <form method="post"><input type="hidden" name="action" value="revoke_session"><input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>"><button class="btn btn-outline-danger btn-sm">Révoquer</button></form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="twofaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="enable_2fa">
        <div class="modal-header"><h3 class="modal-title h5">Authentification à deux facteurs</h3><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="twofa-setup-grid">
                <div class="twofa-qr-wrap"><div class="twofa-qr bg-white rounded"><?php if ($qrImage): ?><img src="<?= e($qrImage) ?>" alt="QR code 2FA"><?php endif; ?></div></div>
                <div>
                    <p class="text-muted">Scannez le QR code avec votre application d’authentification, puis saisissez le code à six chiffres.</p>
                    <label class="form-label">Secret manuel</label><input class="form-control font-monospace mb-3" value="<?= e($setupSecret) ?>" readonly>
                    <label class="form-label">Code 2FA</label><input class="form-control font-monospace" name="twofa_code" inputmode="numeric" pattern="[0-9]{6}" required>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Valider</button></div>
    </form></div></div>
</div>
<?php sodium_render_footer(); ?>
