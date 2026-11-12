<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_login();

$transfer = (string) ($_GET['compose_transfer'] ?? '');
if (!preg_match('/^[A-Za-z0-9-]{16,128}$/', $transfer)) {
    http_response_code(400);
    exit('Fenêtre de rédaction invalide.');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Message mis en file — Sodium</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.css">
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center min-vh-100 p-4">
    <main class="card shadow-sm text-center" style="max-width: 28rem;">
        <div class="card-body p-4">
            <i class="bi bi-check-circle-fill text-success fs-1" aria-hidden="true"></i>
            <h1 class="h4 mt-3">Message placé en file d’envoi</h1>
            <p class="text-body-secondary mb-4">Cette fenêtre va se fermer automatiquement.</p>
            <button class="btn btn-outline-secondary" type="button" onclick="window.close()">Fermer</button>
        </div>
    </main>
    <script>
        try { localStorage.removeItem('sodium-compose-transfer:' + <?= json_encode($transfer, JSON_UNESCAPED_SLASHES) ?>); } catch (error) {}
        window.setTimeout(() => window.close(), 150);
    </script>
</body>
</html>
