<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (current_user()) {
    redirect('/index.php');
}

$error = '';
$username = trim($_POST['username'] ?? '');
$pendingUserId = (int) ($_SESSION['sodium_pending_2fa_user_id'] ?? 0);
$pendingRemember = !empty($_SESSION['sodium_pending_remember_me']);
$turnstileEnabled = sodium_turnstile_enabled();
$turnstileSiteKey = sodium_turnstile_site_key();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'verify_2fa' && $pendingUserId) {
        $code = trim($_POST['twofa_code'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND account_status = 'active'");
        $stmt->execute([$pendingUserId]);
        $user = $stmt->fetch();

        if ($user && !empty($user['twofa_enabled']) && !empty($user['twofa_secret'])
            && twofa_verify_code((string) $user['twofa_secret'], $code)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            unset($_SESSION['sodium_pending_2fa_user_id'], $_SESSION['sodium_pending_remember_me']);
            $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
            if ($pendingRemember) {
                issue_remember_cookie((int) $user['id']);
            }
            $redirect = $_SESSION['sodium_login_redirect'] ?? '/index.php';
            unset($_SESSION['sodium_login_redirect']);
            redirect(is_string($redirect) && str_starts_with($redirect, '/') ? $redirect : '/index.php');
        }
        $error = sodium_t('auth.invalid');
    } else {
        unset($_SESSION['sodium_pending_2fa_user_id'], $_SESSION['sodium_pending_remember_me']);
        $password = $_POST['password'] ?? '';
        $rememberMe = !empty($_POST['remember_me']);
        $turnstileToken = $_POST['cf-turnstile-response'] ?? null;

        if ($turnstileEnabled && !$turnstileToken) {
            $error = 'Le challenge Cloudflare ne s’est pas chargé. Merci de rafraîchir la page.';
        } elseif ($turnstileEnabled && !verify_turnstile($turnstileToken)) {
            $error = 'Validation Cloudflare refusée. Merci de rafraîchir la page puis de réessayer.';
        } elseif ($username === '' || $password === '') {
            $error = 'Veuillez renseigner vos identifiants.';
        } else {
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE account_status = 'active'
                  AND (
                      LOWER(username) = LOWER(?)
                      OR LOWER(email) = LOWER(?)
                      OR LOWER(professional_email) = LOWER(?)
                  )
                LIMIT 1
            ");
            $stmt->execute([$username, $username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, (string) $user['password_hash'])) {
                $_SESSION['sodium_login_redirect'] = $_GET['redirect'] ?? '/index.php';
                if (!empty($user['twofa_enabled'])) {
                    $_SESSION['sodium_pending_2fa_user_id'] = (int) $user['id'];
                    $_SESSION['sodium_pending_remember_me'] = $rememberMe ? 1 : 0;
                    $pendingUserId = (int) $user['id'];
                    $pendingRemember = $rememberMe;
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
                    if ($rememberMe) {
                        issue_remember_cookie((int) $user['id']);
                    }
                    $redirect = $_SESSION['sodium_login_redirect'] ?? '/index.php';
                    unset($_SESSION['sodium_login_redirect']);
                    redirect(is_string($redirect) && str_starts_with($redirect, '/') ? $redirect : '/index.php');
                }
            } else {
            $error = sodium_t('auth.invalid');
            }
        }
    }
}

$showTwofa = !empty($_SESSION['sodium_pending_2fa_user_id']);
$remoteDependencies = sodium_dependency_source() === 'remote';
$bootstrapCss = $remoteDependencies ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css' : '/assets/vendor/bootstrap/bootstrap.min.css';
?>
<!doctype html>
<html lang="<?= e(sodium_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(sodium_t('auth.login.title')) ?> - <?= e(SODIUM_APP_NAME) ?></title>
    <link href="<?=e($bootstrapCss)?>" rel="stylesheet" <?=$remoteDependencies?'onerror="this.onerror=null;this.href=\'/assets/vendor/bootstrap/bootstrap.min.css\'"':''?>>
    <link href="/css/app.css?v=20260820-04" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="shortcut icon" href="/assets/icons/favicon-64.png" type="image/png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-180.png">
    <script>window.SodiumI18n=<?= json_encode(['locale'=>sodium_locale(),'map'=>sodium_browser_translation_map()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
    <?php if (!$showTwofa && $turnstileEnabled): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
</head>
<body class="auth-page">
    <div class="card auth-card shadow-lg">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="brand-mark text-white">M</span>
                <div>
                    <div class="fw-bold fs-4">Sodium</div>
                    <div class="text-muted small">Webmail</div>
                </div>
            </div>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

            <?php if ($showTwofa): ?>
                <form method="post">
                    <input type="hidden" name="action" value="verify_2fa">
                    <div class="mb-3">
                        <label class="form-label" for="twofa_code"><?= e(sodium_t('auth.twofa')) ?></label>
                        <input class="form-control" id="twofa_code" name="twofa_code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required autofocus>
                    </div>
                    <button class="btn btn-danger w-100" type="submit">Valider</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label" for="username">Utilisateur ou adresse mail</label>
                        <input class="form-control" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" placeholder="Nom utilisateur, email personnel ou professionnel" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mot de passe</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me" value="1">
                        <label class="form-check-label" for="remember_me">Se souvenir de moi</label>
                    </div>
                    <?php if ($turnstileEnabled): ?><div class="cf-turnstile mb-3" data-sitekey="<?= e($turnstileSiteKey) ?>" data-theme="light"></div><?php endif; ?>
                    <button class="btn btn-danger w-100" type="submit"><?= e(sodium_t('auth.login')) ?></button>
                </form>
            <?php endif; ?>
            <div class="text-center mt-3">
                <a href="/password-lost.php" class="link-secondary"><?= e(sodium_t('auth.lost')) ?></a>
            </div>
        </div>
    </div>
    <footer class="auth-footer">© <?= date('Y') ?> Jessy System — <?= e(sodium_t('auth.rights')) ?></footer>
    <script src="/js/i18n.js?v=20260819-01"></script>
</body>
</html>
