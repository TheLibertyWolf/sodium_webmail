<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$sent = false;
$resetDone = false;
$error = '';
$requestedEmail = trim((string)($_POST['email'] ?? ''));
$turnstileEnabled = sodium_turnstile_enabled();
$turnstileSiteKey = sodium_turnstile_site_key();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'request');
    if ($action === 'reset') {
        $sent = true;
        $password = (string)($_POST['password'] ?? '');
        if (!hash_equals($password,(string)($_POST['password_confirm'] ?? ''))) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            try {
                $result = password_reset_complete($requestedEmail,(string)($_POST['code'] ?? ''),$password);
                $resetDone = !empty($result['ok']);
                if (!$resetDone) $error = (string)$result['message'];
            } catch (Throwable $exception) {
                error_log('[Sodium password reset] '.$exception->getMessage());
                $error = 'Réinitialisation momentanément indisponible.';
            }
        }
    } elseif ($requestedEmail !== '') {
        $turnstileToken = (string)($_POST['cf-turnstile-response'] ?? '');
        if ($turnstileEnabled && ($turnstileToken === '' || !verify_turnstile($turnstileToken))) {
            $error = 'La vérification de sécurité a échoué. Veuillez réessayer.';
        } else {
            try {
                password_reset_request_code($requestedEmail,'sodium');
            } catch (Throwable $exception) {
                error_log('[Sodium password reset queue] '.$exception->getMessage());
                $error=str_contains($exception->getMessage(),'Aucun moyen d’envoi système')?'La procédure de mot de passe perdu est indisponible : aucun compte SMTP, accès Brevo ou envoi PHP utilisable n’est configuré.':'L’envoi du code est momentanément indisponible.';
            }
            $sent = $error==='';
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mot de passe perdu - <?= e(SODIUM_APP_NAME) ?></title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/css/app.css?v=20260729-01" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="shortcut icon" href="/assets/icons/favicon-64.png" type="image/png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-180.png">
    <?php if ($turnstileEnabled): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
</head>
<body class="auth-page">
    <div class="card auth-card shadow-lg">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="brand-mark text-white">M</span>
                <h1 class="h4 mb-0">Mot de passe perdu</h1>
            </div>
            <?php if ($resetDone): ?>
                <div class="alert alert-success">Votre mot de passe a été modifié. Vous pouvez maintenant vous connecter.</div>
                <a href="/login.php" class="btn btn-danger w-100">Retour connexion</a>
            <?php elseif ($sent): ?>
                <div class="alert alert-success">Si le compte existe, un code de réinitialisation a été envoyé.</div>
                <?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="reset">
                    <div class="mb-3"><label class="form-label" for="email">Adresse mail personnelle ou professionnelle</label><input class="form-control" id="email" name="email" type="email" value="<?= e($requestedEmail) ?>" required></div>
                    <div class="mb-3"><label class="form-label" for="code">Code à 6 chiffres</label><input class="form-control text-center font-monospace fs-4" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div>
                    <div class="mb-3"><label class="form-label" for="password">Nouveau mot de passe</label><input class="form-control" id="password" name="password" type="password" minlength="10" autocomplete="new-password" required></div>
                    <div class="mb-3"><label class="form-label" for="passwordConfirm">Confirmer le mot de passe</label><input class="form-control" id="passwordConfirm" name="password_confirm" type="password" minlength="10" autocomplete="new-password" required></div>
                    <button class="btn btn-danger w-100" type="submit">Modifier le mot de passe</button>
                </form>
                <div class="text-center mt-3"><a href="/password-lost.php" class="link-secondary">Demander un nouveau code</a></div>
            <?php else: ?>
                <?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="request">
                    <div class="mb-3">
                        <label class="form-label" for="email">Adresse mail personnelle ou professionnelle</label>
                        <input class="form-control" id="email" name="email" type="email" required>
                    </div>
                    <?php if ($turnstileEnabled): ?><div class="cf-turnstile mb-3" data-sitekey="<?= e($turnstileSiteKey) ?>" data-theme="light"></div><?php endif; ?>
                    <button class="btn btn-danger w-100" type="submit">Envoyer le code</button>
                </form>
                <div class="text-center mt-3"><a href="/login.php" class="link-secondary">Retour connexion</a></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
