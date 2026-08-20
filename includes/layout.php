<?php
declare(strict_types=1);

function sodium_nav_active(string $path): string
{
    return ($_SERVER['SCRIPT_NAME'] ?? '') === $path ? 'active' : '';
}

function sodium_color_contrast(string $color): string
{
    $hex = ltrim($color, '#');
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) return '#ffffff';
    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));
    return (($red * 299 + $green * 587 + $blue * 114) / 1000) >= 150 ? '#212529' : '#ffffff';
}

function sodium_render_alternate_navigation(string $theme, bool $licenseValid, ?array $activeMailAccount, array $mailAccounts, array $folders, string $activeFolder, int $unifiedUnread, int $scheduledCount): void
{
    if(!in_array($theme,['outlook','roundcube'],true))return;
    $hasAccounts=(bool)$mailAccounts;
    if($theme==='outlook'): ?>
        <nav class="outlook-appbar" aria-label="Navigation Outlook">
            <a class="outlook-app-brand" href="/index.php"><span class="brand-mark">M</span><strong>Sodium</strong></a>
            <button class="btn outlook-compose" type="button" data-bs-toggle="modal" data-bs-target="#composeModal" <?=$hasAccounts?'':'disabled'?>><i class="bi bi-pencil-square"></i><span>Nouveau message</span></button>
            <?php if($licenseValid): ?><div class="outlook-primary-links"><a class="outlook-nav-link <?=sodium_nav_active('/index.php')?>" href="/index.php"><i class="bi bi-inboxes"></i><span>Réception</span><?php if($unifiedUnread):?><b><?=$unifiedUnread?></b><?php endif;?></a><a class="outlook-nav-link <?=sodium_nav_active('/starred.php')?>" href="/starred.php"><i class="bi bi-star"></i><span>Marqués</span></a><a class="outlook-nav-link <?=sodium_nav_active('/outbox.php')?>" href="/outbox.php"><i class="bi bi-send"></i><span>Envoi</span><?php if($scheduledCount):?><b><?=$scheduledCount?></b><?php endif;?></a></div><?php endif; ?>
            <?php if($hasAccounts): ?><div class="dropdown"><button class="outlook-menu-button" data-bs-toggle="dropdown" type="button"><?php if(!empty($activeMailAccount['icon_path'])):?><img class="mail-account-image" src="<?=e($activeMailAccount['icon_path'])?>" alt=""><?php else:?><i class="bi bi-envelope-at"></i><?php endif;?><span><?=e($activeMailAccount['display_name']?:$activeMailAccount['email_address'])?></span><i class="bi bi-chevron-down"></i></button><div class="dropdown-menu outlook-dropdown"><?php foreach($mailAccounts as $account):?><a class="dropdown-item" href="/mailbox.php?account_id=<?=(int)$account['id']?>&amp;folder=INBOX"><strong><?=e($account['display_name']?:$account['email_address'])?></strong><small><?=e($account['email_address'])?></small></a><?php endforeach;?></div></div>
            <div class="dropdown"><button class="outlook-menu-button" data-bs-toggle="dropdown" type="button"><i class="bi bi-folder2-open"></i><span>Dossiers</span><i class="bi bi-chevron-down"></i></button><div class="dropdown-menu outlook-dropdown folder-menu"><?php foreach($folders as $folder):?><a class="dropdown-item <?=$activeFolder===(string)$folder['key']?'active':''?>" href="/mailbox.php?account_id=<?=(int)$activeMailAccount['id']?>&amp;folder=<?=rawurlencode((string)$folder['key'])?>"><i class="bi bi-<?=e($folder['icon']??'folder')?>"></i><span><?=e($folder['label']??$folder['key'])?></span><?php if(!empty($folder['unread'])):?><b><?=(int)$folder['unread']?></b><?php endif;?></a><?php endforeach;?></div></div><?php endif; ?>
            <div class="dropdown ms-auto"><button class="outlook-icon-button" data-bs-toggle="dropdown" type="button" title="Paramètres"><i class="bi bi-gear"></i></button><div class="dropdown-menu dropdown-menu-end outlook-dropdown"><a class="dropdown-item" href="/mail-accounts.php"><i class="bi bi-envelope-at"></i> Comptes mails</a><?php if(sodium_can('sodium_signatures_view')):?><a class="dropdown-item" href="/signatures.php"><i class="bi bi-person-vcard"></i> Signatures</a><?php endif;?><?php if(sodium_can('sodium_labels_view')):?><a class="dropdown-item" href="/tags.php"><i class="bi bi-tags"></i> Tags</a><?php endif;?><?php if(sodium_can('sodium_templates_view')):?><a class="dropdown-item" href="/templates.php"><i class="bi bi-chat-square-text"></i> Modèles</a><?php endif;?><?php if(sodium_can('sodium_settings_view')):?><a class="dropdown-item" href="/messages.php"><i class="bi bi-envelope-gear"></i> Messages</a><?php endif;?><hr class="dropdown-divider"><?php if(sodium_can('sodium_accounts_view')):?><a class="dropdown-item" href="/admin/mail-accounts.php"><i class="bi bi-envelope-check"></i> Gestion des comptes</a><?php endif;?><?php if(sodium_can('sodium_full_access')):?><a class="dropdown-item" href="/admin/users.php"><i class="bi bi-people"></i> Utilisateurs</a><?php endif;?><?php if(sodium_can('sodium_general_settings_view')||sodium_can('sodium_update')):?><a class="dropdown-item" href="/admin/settings.php"><i class="bi bi-sliders"></i> Paramètres généraux</a><?php endif;?><?php if(sodium_can('sodium_security_settings_view')):?><a class="dropdown-item" href="/admin/security.php"><i class="bi bi-shield-lock"></i> Sécurité</a><?php endif;?><?php if(sodium_can('licence')):?><a class="dropdown-item" href="/admin/license.php"><i class="bi bi-key"></i> Licence</a><?php endif;?></div></div>
            <a class="outlook-icon-button" href="/profile.php" title="Profil"><i class="bi bi-person-circle"></i></a>
            <form method="post" action="/logout.php"><button class="outlook-icon-button" type="submit" title="Se déconnecter"><i class="bi bi-box-arrow-right"></i></button></form>
        </nav>
    <?php else: ?>
        <nav class="roundcube-app-rail" aria-label="Applications Roundcube"><a class="roundcube-logo" href="/index.php">M</a><a class="<?=sodium_nav_active('/index.php')?>" href="/index.php" title="Courrier"><i class="bi bi-envelope-fill"></i><span>Courrier</span></a><button type="button" data-bs-toggle="modal" data-bs-target="#composeModal" <?=$hasAccounts?'':'disabled'?> title="Rédiger"><i class="bi bi-pencil-square"></i><span>Rédiger</span></button><a class="<?=sodium_nav_active('/starred.php')?>" href="/starred.php" title="Marqués"><i class="bi bi-star-fill"></i><span>Marqués</span></a><a class="<?=sodium_nav_active('/outbox.php')?>" href="/outbox.php" title="Envoi"><i class="bi bi-send-fill"></i><span>Envoi</span></a><a href="/messages.php" title="Paramètres"><i class="bi bi-gear-fill"></i><span>Réglages</span></a><a href="/profile.php" title="Profil"><i class="bi bi-person-fill"></i><span>Profil</span></a><form method="post" action="/logout.php"><button type="submit" title="Se déconnecter"><i class="bi bi-box-arrow-right"></i><span>Quitter</span></button></form></nav>
        <aside class="roundcube-folder-pane"><div class="roundcube-pane-title"><strong>Dossiers</strong><a href="/messages.php" title="Réglages"><i class="bi bi-gear"></i></a></div><?php if($hasAccounts):?><div class="dropdown mb-3"><button class="roundcube-account" data-bs-toggle="dropdown" type="button"><span><?=e($activeMailAccount['display_name']?:$activeMailAccount['email_address'])?></span><i class="bi bi-chevron-down"></i></button><div class="dropdown-menu w-100"><?php foreach($mailAccounts as $account):?><a class="dropdown-item" href="/mailbox.php?account_id=<?=(int)$account['id']?>&amp;folder=INBOX"><?=e($account['display_name']?:$account['email_address'])?></a><?php endforeach;?></div></div><nav class="roundcube-folders"><?php foreach($folders as $folder):?><a class="<?=$activeFolder===(string)$folder['key']?'active':''?>" href="/mailbox.php?account_id=<?=(int)$activeMailAccount['id']?>&amp;folder=<?=rawurlencode((string)$folder['key'])?>"><i class="bi bi-<?=e($folder['icon']??'folder')?>"></i><span><?=e($folder['label']??$folder['key'])?></span><?php if(!empty($folder['unread'])):?><b><?=(int)$folder['unread']?></b><?php endif;?></a><?php endforeach;?></nav><?php else:?><div class="small text-muted p-3">Aucun compte mail</div><?php endif;?></aside>
    <?php endif;
}

function sodium_render_header(string $title): void
{
    global $pdo, $sodiumDefaultBrowserTitle;
    $user = current_user();
    $instanceSettings=sodium_instance_settings();$instanceName=trim((string)$instanceSettings['instance_name'])?:'Sodium';
    $remoteDependencies=(string)($instanceSettings['dependency_source']??'local')==='remote';
    $bootstrapCss=$remoteDependencies?'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css':'/assets/vendor/bootstrap/bootstrap.min.css';
    $bootstrapIconsCss=$remoteDependencies?'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css':'/assets/vendor/bootstrap-icons/bootstrap-icons.css';
    $bootstrapJs=$remoteDependencies?'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js':'/assets/vendor/bootstrap/bootstrap.bundle.min.js';
    $appLicense = sodium_license_public_info();
    $licenseValid = !empty($appLicense['is_valid']);
    $theme = in_array(($user['theme'] ?? 'light'), ['light','dark','outlook','roundcube'], true) ? (string)$user['theme'] : 'light';
    $bootstrapTheme = $theme === 'dark' ? 'dark' : 'light';
    $activeMailAccount = $licenseValid ? sodium_active_mail_account() : null;
    if ($activeMailAccount && !empty($activeMailAccount['password_cipher'])) {
        $activeMailAccount = sodium_refresh_account_cache((int) $activeMailAccount['id']);
    }
    $mailAccounts = $licenseValid ? sodium_accessible_mail_accounts() : [];
    $hasSodiumUniverse = false;
    if ($user) {
        $sodiumUniverseStmt = $pdo->prepare('SELECT 1 FROM sodium_user_mail_accounts WHERE user_id=? LIMIT 1');
        $sodiumUniverseStmt->execute([(int)$user['id']]);
        $hasSodiumUniverse = (bool)$sodiumUniverseStmt->fetchColumn();
    }
    foreach ($mailAccounts as &$listedAccount) {
        if ((int) $listedAccount['id'] === (int) ($activeMailAccount['id'] ?? 0)) $listedAccount = array_merge($listedAccount, $activeMailAccount);
    }
    unset($listedAccount);
    $folders = sodium_account_folders($activeMailAccount);
    $unifiedUnread = array_sum(array_map(static fn(array $account): int => (int) ($account['unread_count'] ?? 0), $mailAccounts));
    $scheduledCount = 0;
    if ($mailAccounts) {
        $outboxAccountIds = array_map('intval', array_column($mailAccounts, 'id'));
        $outboxCountStmt = $pdo->prepare('SELECT COUNT(*) FROM sodium_composed_messages WHERE user_id=? AND status=\'scheduled\' AND mail_account_id IN (' . implode(',', array_fill(0, count($outboxAccountIds), '?')) . ')');
        $outboxCountStmt->execute(array_merge([(int) ($user['id'] ?? 0)], $outboxAccountIds));
        $scheduledCount = (int) $outboxCountStmt->fetchColumn();
    }
    $activeFolder = (string) ($_GET['folder'] ?? 'INBOX');
    $messages = flash();
    $updateStatus=sodium_can('sodium_update')?sodium_update_status(false):null;
    $defaultBrowserTitle = $title . ' - '.$instanceName;
    $sodiumDefaultBrowserTitle = $defaultBrowserTitle;
    $browserTitle = $unifiedUnread > 0
        ? $unifiedUnread . ' nouveau' . ($unifiedUnread > 1 ? 'x' : '') . ' message' . ($unifiedUnread > 1 ? 's' : '') . ' - '.$instanceName
        : $defaultBrowserTitle;
    ?>
    <!doctype html>
    <html lang="<?= e(sodium_locale()) ?>" data-bs-theme="<?= e($bootstrapTheme) ?>" data-sodium-theme="<?= e($theme) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?= e(sodium_csrf_token()) ?>">
        <title><?= e($browserTitle) ?></title>
        <link href="<?=e($bootstrapCss)?>" rel="stylesheet" <?=$remoteDependencies?'onerror="this.onerror=null;this.href=\'/assets/vendor/bootstrap/bootstrap.min.css\'"':''?>>
        <link href="<?=e($bootstrapIconsCss)?>" rel="stylesheet" <?=$remoteDependencies?'onerror="this.onerror=null;this.href=\'/assets/vendor/bootstrap-icons/bootstrap-icons.css\'"':''?>>
        <link href="/css/app.css?v=20260820-05" rel="stylesheet">
        <link href="/css/themes.css?v=20260820-02" rel="stylesheet">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#172033">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="shortcut icon" href="/assets/icons/favicon-64.png" type="image/png">
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-180.png">
        <link rel="icon" sizes="192x192" href="/assets/icons/pwa-192.png">
        <script>window.SodiumI18n=<?= json_encode(['locale'=>sodium_locale(),'map'=>sodium_browser_translation_map()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
        <script>
            try {
                if (localStorage.getItem('sodiumSidebarCollapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (error) {}
        </script>
    </head>
    <body>
        <?php sodium_render_alternate_navigation($theme,$licenseValid,$activeMailAccount,$mailAccounts,$folders,$activeFolder,$unifiedUnread,$scheduledCount); ?>
        <aside class="sidebar" id="mainSidebar">
            <div class="brand">
                <span class="brand-mark">M</span>
                <span class="brand-text"><span class="brand-title"><?=e($instanceName)?></span><small>Webmail</small></span>
                <button class="btn sidebar-toggle" type="button" id="sidebarToggle" aria-label="Réduire le menu">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
            </div>

            <?php if($licenseValid): ?>
            <?php if ($mailAccounts): ?>
            <nav class="nav flex-column gap-1 main-nav">
                <a class="nav-link <?= sodium_nav_active('/index.php') ?>" href="/index.php">
                    <i class="bi bi-inboxes"></i><span>Boîte de réception unifiée</span>
                    <span class="badge rounded-pill text-bg-danger ms-auto <?= $unifiedUnread ? '' : 'd-none' ?>" data-unified-unread><?= $unifiedUnread ?></span>
                </a>
                <a class="nav-link <?= sodium_nav_active('/starred.php') ?>" href="/starred.php">
                    <i class="bi bi-star"></i><span>Messages marqués</span>
                </a>
                <a class="nav-link <?= sodium_nav_active('/outbox.php') ?>" href="/outbox.php">
                    <i class="bi bi-send"></i><span>Boîte d’envoi</span>
                    <span class="badge rounded-pill text-bg-primary ms-auto <?= $scheduledCount?'':'d-none' ?>" data-outbox-count><?= $scheduledCount ?></span>
                </a>
            </nav>
            <?php endif; ?>

            <?php if ($mailAccounts): ?>
                <div class="mail-context-card">
                    <div class="mail-account-switcher">
                        <label class="form-label">Compte mail</label>
                        <div class="dropdown">
                            <button class="btn mail-account-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (!empty($activeMailAccount['icon_path'])): ?><img class="mail-account-image" style="--account-color:<?= e($activeMailAccount['label_color']) ?>" src="<?= e($activeMailAccount['icon_path']) ?>" alt=""><?php else: ?><span class="mail-account-avatar" style="--account-color:<?= e($activeMailAccount['label_color']) ?>"><?= e(strtoupper(substr((string) ($activeMailAccount['email_address'] ?? 'M'), 0, 1))) ?></span><?php endif; ?>
                                <span class="mail-account-label"><?= e($activeMailAccount['display_name'] ?: $activeMailAccount['email_address']) ?></span>
                                <i class="bi bi-chevron-down ms-auto"></i>
                            </button>
                            <div class="dropdown-menu mail-account-menu">
                                <?php foreach ($mailAccounts as $account): ?>
                                    <a class="dropdown-item mail-account-item <?= (int) $account['id'] === (int) ($activeMailAccount['id'] ?? 0) ? 'active' : '' ?>" href="/mailbox.php?account_id=<?= (int) $account['id'] ?>&amp;folder=INBOX">
                                        <?php if (!empty($account['icon_path'])): ?><img class="mail-account-image" style="--account-color:<?= e($account['label_color']) ?>" src="<?= e($account['icon_path']) ?>" alt=""><?php else: ?><span class="mail-account-avatar" style="--account-color:<?= e($account['label_color']) ?>"><?= e(strtoupper(substr((string) $account['email_address'], 0, 1))) ?></span><?php endif; ?>
                                        <span class="min-width-0"><strong><?= e($account['display_name'] ?: $account['email_address']) ?></strong><small><?= e($account['email_address']) ?></small></span>
                                        <span class="badge rounded-pill <?= !empty($account['unread_count']) ? '' : 'd-none' ?>" data-account-unread="<?= (int)$account['id'] ?>" style="background-color:<?= e($account['label_color']) ?>;color:<?= e(sodium_color_contrast((string) $account['label_color'])) ?>"><?= (int) $account['unread_count'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <nav class="nav flex-column gap-1 folder-nav">
                        <?php foreach ($folders as $folder): ?>
                            <a class="nav-link <?= sodium_nav_active('/mailbox.php') && $activeFolder === (string) $folder['key'] ? 'active' : '' ?>" href="/mailbox.php?account_id=<?= (int) $activeMailAccount['id'] ?>&amp;folder=<?= rawurlencode((string) $folder['key']) ?>" data-mail-folder-drop data-account="<?= (int)$activeMailAccount['id'] ?>" data-folder="<?= e($folder['key']) ?>">
                                <i class="bi bi-<?= e($folder['icon'] ?? 'folder') ?>"></i>
                                <span class="folder-link-label" title="<?= e($folder['label'] ?? $folder['key']) ?>"><?= e($folder['label'] ?? $folder['key']) ?></span>
                                <?php $isInboxFolder = strtoupper((string)$folder['key']) === 'INBOX'; ?>
                                <?php if ($isInboxFolder || !empty($folder['unread'])): ?><span class="badge rounded-pill ms-auto <?= !empty($folder['unread']) ? '' : 'd-none' ?>" <?= $isInboxFolder ? 'data-active-inbox-unread="'.(int)$activeMailAccount['id'].'"' : '' ?> style="background-color:<?= e($activeMailAccount['label_color']) ?>;color:<?= e(sodium_color_contrast((string) $activeMailAccount['label_color'])) ?>"><?= (int) $folder['unread'] ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <?php $quota = sodium_format_quota($activeMailAccount['quota_used_kb'] !== null ? (int) $activeMailAccount['quota_used_kb'] : null, $activeMailAccount['quota_limit_kb'] !== null ? (int) $activeMailAccount['quota_limit_kb'] : null); ?>
                    <div class="mail-quota"><span><?= e($quota['label']) ?></span><div class="progress"><div class="progress-bar <?= $quota['percent'] >= 90 ? 'bg-danger' : '' ?>" style="width:<?= (int) $quota['percent'] ?>%"></div></div></div>
                </div>
            <?php else: ?>
                <?php if (sodium_can('sodium_accounts_manage')): ?>
                    <a class="first-mailbox-card" href="/admin/mail-accounts.php">
                        <span class="first-mailbox-icon"><i class="bi bi-envelope-plus"></i></span>
                        <strong>Ajouter votre première boîte mail</strong>
                        <small>Configurer un compte IMAP et SMTP</small>
                    </a>
                <?php else: ?>
                    <div class="mail-empty-state"><i class="bi bi-envelope-x"></i><span>Aucun compte mail attribué</span></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($licenseValid): ?>
                <div class="sidebar-separator"></div>
                <div class="admin-nav">
                    <div class="module-title">Paramètres</div>
                    <a class="nav-link <?= sodium_nav_active('/mail-accounts.php') ?>" href="/mail-accounts.php"><i class="bi bi-envelope-at"></i><span>Comptes mails</span></a>
                    <?php if(sodium_can('sodium_signatures_view')): ?><a class="nav-link <?= sodium_nav_active('/signatures.php') ?>" href="/signatures.php"><i class="bi bi-person-vcard"></i><span>Signatures</span></a><?php endif; ?>
                    <?php if(sodium_can('sodium_labels_view')): ?><a class="nav-link <?= sodium_nav_active('/tags.php') ?>" href="/tags.php"><i class="bi bi-tags"></i><span>Tags</span></a><?php endif; ?>
                    <?php if(sodium_can('sodium_templates_view')): ?><a class="nav-link <?= sodium_nav_active('/templates.php') ?>" href="/templates.php"><i class="bi bi-chat-square-text"></i><span>Modèles de réponses</span></a><?php endif; ?>
                    <?php if(sodium_can('sodium_settings_view')): ?><a class="nav-link <?= sodium_nav_active('/messages.php') ?>" href="/messages.php"><i class="bi bi-envelope"></i><span>Messages</span></a><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (sodium_can('sodium_full_access') || sodium_can('sodium_accounts_view') || sodium_can('sodium_general_settings_view') || sodium_can('sodium_security_settings_view') || sodium_can('sodium_update') || sodium_can('licence')): ?>
                <div class="sidebar-separator"></div>
                <div class="admin-nav">
                    <div class="module-title">Administration</div>
                    <?php if (sodium_can('sodium_full_access')): ?><a class="nav-link <?= sodium_nav_active('/admin/users.php') ?>" href="/admin/users.php"><i class="bi bi-people"></i><span>Utilisateurs</span></a><?php endif; ?>
                    <?php if (sodium_can('sodium_accounts_view')): ?><a class="nav-link <?= sodium_nav_active('/admin/mail-accounts.php') ?>" href="/admin/mail-accounts.php"><i class="bi bi-envelope-at"></i><span>Gestion Comptes mails</span></a><?php endif; ?>
                    <?php if (sodium_can('sodium_general_settings_view')||sodium_can('sodium_update')): ?><a class="nav-link <?= sodium_nav_active('/admin/settings.php')||sodium_nav_active('/admin/update.php')?'active':'' ?>" href="/admin/settings.php"><i class="bi bi-sliders"></i><span>Paramètres généraux</span></a><?php endif; ?>
                    <?php if (sodium_can('sodium_security_settings_view')): ?><a class="nav-link <?= sodium_nav_active('/admin/security.php') ?>" href="/admin/security.php"><i class="bi bi-shield-lock"></i><span>Paramètres de sécurité</span></a><?php endif; ?>
                    <?php if (sodium_can('licence')): ?><a class="nav-link <?= sodium_nav_active('/admin/license.php') ?>" href="/admin/license.php"><i class="bi bi-key"></i><span>Licence</span></a><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php else: ?>
                <div class="sidebar-separator"></div>
                <?php if(sodium_can('licence')): ?><div class="admin-nav"><div class="module-title">Administration</div><a class="nav-link <?= sodium_nav_active('/admin/license.php') ?>" href="/admin/license.php"><i class="bi bi-key"></i><span>Licence</span></a></div><?php endif; ?>
            <?php endif; ?>

            <div class="sidebar-footer">
                <a class="nav-link <?= sodium_nav_active('/profile.php') ?>" href="/profile.php">
                    <i class="bi bi-person-gear"></i><span>Profil <span class="sidebar-user-name"><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '')) ?></span></span>
                </a>
                <button class="nav-link w-100 text-start" type="button" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="bi bi-info-circle"></i><span>À propos</span></button>
                <form method="post" action="/logout.php"><button class="nav-link logout-link w-100 text-start" type="submit"><i class="bi bi-box-arrow-left"></i><span>Se déconnecter</span></button></form>
            </div>
        </aside>
        <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><div><div class="text-danger small fw-semibold text-uppercase">À propos</div><h2 class="modal-title h4 mb-0" id="aboutModalLabel">Sodium</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-4"><span class="brand-mark flex-shrink-0">M</span><div><strong class="d-block fs-5">Sodium</strong><span class="text-muted">Webmail professionnel privé</span></div><span class="badge text-bg-danger ms-auto">Version 1.3.0</span></div>
                    <h3 class="h6">Édition et réalisation</h3><p><strong>Sodium Webmail</strong> est un logiciel SaaS conçu, édité et maintenu par <strong>Jessy System</strong>. Il centralise l’accès aux comptes de messagerie attribués aux utilisateurs autorisés dans un environnement professionnel privé.</p>
                    <h3 class="h6">Licence d’exploitation</h3><p>Cette instance est mise à disposition dans le cadre d’une licence d’exploitation SaaS <code><?=e($appLicense['license_type']??'non enregistrée')?></code> concédée à <code><?=e($appLicense['rights_holder']??'ayant droit non enregistré')?></code><?php if(!empty($appLicense['registered_at'])): ?>, enregistrée le <code><?=e(sodium_format_date($appLicense['registered_at'],'d/m/Y','date inconnue'))?></code><?php endif; ?><?php if(!empty($appLicense['expires_at'])&&$appLicense['expires_at']<'9999-01-01'): ?> et valable jusqu’au <code><?=e(sodium_format_date($appLicense['expires_at'],'d/m/Y','date inconnue'))?></code><?php elseif(!empty($appLicense['expires_at'])): ?>, pour une durée <code>perpétuelle</code><?php endif; ?>. Elle confère un droit personnel d’accès et d’utilisation du service sur le domaine autorisé, dans les limites fonctionnelles, techniques et contractuelles convenues. Elle n’emporte aucune cession des droits de propriété intellectuelle sur le logiciel.</p>
                    <h3 class="h6">Propriété intellectuelle et étendue des droits</h3><p>L’architecture, le code, l’interface, les développements, les textes, les éléments graphiques, les bases de données et les composants de Sodium demeurent protégés. Sauf autorisation écrite ou exception prévue par la loi, sont interdits la reproduction, la mise à disposition de tiers, la sous-licence, la commercialisation, l’extraction substantielle, l’adaptation ou le contournement des dispositifs techniques de licence. Les droits strictement nécessaires à l’utilisation conforme du logiciel par l’ayant droit demeurent réservés conformément aux dispositions légales applicables.</p>
                    <h3 class="h6">Protection et usages interdits</h3><p><strong>Sodium, dans toutes ses composantes, est protégé par le droit d’auteur, le droit des logiciels, le droit des bases de données et, plus généralement, par les règles françaises, européennes et internationales relatives à la propriété intellectuelle.</strong> Aucun droit d’utilisation, d’exploitation, de reproduction, de modification, de diffusion, de traduction, d’adaptation, de rétro-ingénierie, de décompilation, de sous-licence ou de mise à disposition ne peut être présumé ou acquis en dehors des droits expressément accordés par une licence valide et par les actes contractuels conclus avec Jessy System.</p>
                    <p>Toute installation, utilisation ou exploitation de Sodium sans licence valide, au-delà de son périmètre, après sa suspension ou sa résiliation, ou sans le consentement préalable, exprès et écrit de Jessy System est strictement interdite. Sont également interdits toute suppression ou altération des mentions de propriété, tout partage non autorisé d’accès ou de clé, toute tentative de neutralisation, de dissimulation ou de contournement du mécanisme de contrôle de licence, ainsi que toute copie ou réutilisation non autorisée, même partielle, du logiciel ou de ses éléments.</p>
                    <p>Jessy System se réserve le droit de contrôler la validité et le respect de la licence, de suspendre ou de désactiver les accès en cas d’usage irrégulier et de prendre toute mesure utile à la protection de ses droits. Toute atteinte constatée est susceptible d’entraîner la cessation immédiate de l’utilisation, la réparation intégrale du préjudice subi et l’engagement de toute action civile ou pénale appropriée, y compris en référé, sans préjudice de tous autres droits et recours prévus par la loi ou le contrat.</p>
                    <h3 class="h6">Secret des correspondances et confidentialité</h3><p>Les messages, pièces jointes, contacts et informations techniques accessibles dans Sodium sont susceptibles de contenir des données confidentielles. Leur consultation et leur utilisation doivent respecter le secret des correspondances, les habilitations accordées et les règles internes de l’ayant droit.</p>
                    <h3 class="h6">Données et sécurité</h3><p>Chaque utilisateur est responsable de la protection de ses identifiants et de l’usage de ses comptes mails. Les accès et opérations peuvent être journalisés à des fins de sécurité, de traçabilité, de maintenance et de prévention des abus. Toute tentative d’accès non autorisé, d’extraction massive ou de contournement des protections est interdite.</p>
                    <h3 class="h6">Disponibilité, responsabilité et services tiers</h3><p>La licence autorise l’usage du logiciel mais ne constitue pas, à elle seule, une garantie d’absence d’interruption ou d’anomalie. Les utilisateurs doivent vérifier les destinataires, le contenu et les pièces jointes avant tout envoi. La responsabilité de Jessy System ne saurait être engagée au titre d’un usage non conforme, d’une erreur de manipulation, d’un défaut imputable à l’hébergement, au réseau, à un serveur de messagerie ou à tout autre service tiers, sous réserve des dispositions impératives applicables.</p>
                    <h3 class="h6">Protection des données</h3><p>L’ayant droit demeure responsable de la détermination des finalités et des habilitations associées aux traitements réalisés au moyen de Sodium. Les mesures de sécurité, de confidentialité, de sauvegarde et de gestion des incidents doivent être organisées conformément aux rôles effectifs des parties et à la réglementation applicable. Les présentes informations ne remplacent pas un contrat de licence, un accord de niveau de service ou un accord de traitement des données lorsqu’un tel document est requis.</p>
                    <div class="border rounded p-3 bg-body-tertiary"><strong>Copyright © 2026 Jessy System — Tous droits réservés.</strong><br><span class="text-muted">Produit : <code><?=e($appLicense['product_name']??'Sodium Webmail')?></code> · Domaine autorisé : <code><?=e($appLicense['allowed_domain']??'non enregistré')?></code> · Statut : <code><?=e(!empty($appLicense['is_valid'])?'licence active':'activation requise')?></code>.</span></div>
                </div>
                <div class="modal-footer"><span class="text-muted small me-auto">Sodium 1.3.0</span><button class="btn btn-danger" type="button" data-bs-dismiss="modal">Fermer</button></div>
            </div></div>
        </div>

        <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Fermer le menu"></button>
        <main class="app-main">
            <header class="topbar">
                <button class="btn btn-outline-secondary mobile-menu-toggle" type="button" id="mobileMenuToggle" aria-label="Afficher le menu" aria-controls="mainSidebar" aria-expanded="false">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-title-wrap">
                    <div>
                        <div class="text-muted small">Webmail privé<?= $activeMailAccount ? ' · ' . e($activeMailAccount['email_address']) : '' ?></div>
                        <h1><?= e($title) ?></h1>
                    </div>
                </div>
                <?php if($licenseValid): ?><button class="btn btn-outline-secondary notification-toggle" id="notificationToggle" type="button" title="Notifications désactivées" aria-label="Notifications désactivées" aria-pressed="false"><i class="bi bi-bell-slash"></i></button>
                <?php endif; ?>
            </header>
            <?php if(!empty($updateStatus['available'])):?><a class="update-available-banner" href="/admin/settings.php"><i class="bi bi-cloud-arrow-down-fill"></i><span>Une mise à jour Sodium <strong><?=e($updateStatus['latest'])?></strong> est disponible.</span><span class="ms-auto">Consulter <i class="bi bi-chevron-right"></i></span></a><?php endif;?>
            <section class="content">
                <div class="toast-container position-fixed bottom-0 end-0 p-3 app-toast-container" id="appToastContainer">
                    <?php foreach ($messages as $type => $message): $toastType = in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'warning'; ?>
                        <div class="toast border-0 text-bg-<?= e($toastType) ?>" role="<?= $toastType==='danger'?'alert':'status' ?>" <?= $toastType==='danger'?'data-bs-autohide="false"':'data-bs-delay="5000"' ?>>
                            <div class="d-flex"><div class="toast-body"><?php if($toastType==='danger'): ?><strong class="d-block mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Erreur</strong><?php endif; ?><?= e($message) ?></div><button type="button" class="btn-close <?= in_array($toastType, ['danger','success'], true) ? 'btn-close-white' : '' ?> me-2 m-auto" data-bs-dismiss="toast"></button></div>
                        </div>
                    <?php endforeach; ?>
                </div>
    <?php
}

function sodium_render_footer(): void
{
    global $pdo, $sodiumDefaultBrowserTitle;
    $remoteDependencies=sodium_dependency_source()==='remote';
    $bootstrapJs=$remoteDependencies?'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js':'/assets/vendor/bootstrap/bootstrap.bundle.min.js';
    $sodiumDefaultBrowserTitle = (string) ($sodiumDefaultBrowserTitle ?? 'Sodium');
    $accounts = sodium_accessible_mail_accounts();
    $user = current_user();
    $sodiumSettings = sodium_user_settings((int)($user['id'] ?? 0));
    $sendAccountIds=array_map('intval',array_column(array_values(array_filter($accounts,static fn(array $account):bool=>!empty($account['can_send']))),'id'));
    $defaultComposeAccountId=(int)($sodiumSettings['default_compose_account_id']??0);
    if(!in_array($defaultComposeAccountId,$sendAccountIds,true))$defaultComposeAccountId=$sendAccountIds[0]??0;
    $signatures = [];
    $replyTemplates = [];
    $readerFolders = [];
    $readerTags = [];
    $composeDraft = null;
    $hasActiveAutoReply = false;
    if ($accounts) {
        foreach ($accounts as $readerAccount) {
            $readerFolders[(int)$readerAccount['id']] = array_map(static fn(array $folder):array=>[
                'key'=>(string)($folder['key']??''),
                'label'=>(string)($folder['label']??$folder['key']??''),
            ], sodium_account_folders($readerAccount));
        }
        $readerAccountIds=array_map('intval',array_column($accounts,'id'));
        $readerTagVisibility=sodium_can_manage_all('sodium_labels')?'1=1':'(created_by=? OR is_shared=1)';
        $readerTagStmt=$pdo->prepare('SELECT t.id,ta.mail_account_id,t.name,t.color FROM sodium_tags t INNER JOIN sodium_tag_accounts ta ON ta.tag_id=t.id WHERE ta.mail_account_id IN ('.implode(',',array_fill(0,count($readerAccountIds),'?')).') AND '.$readerTagVisibility.' ORDER BY t.name');
        $readerTagParams=$readerAccountIds;if(!sodium_can_manage_all('sodium_labels'))$readerTagParams[]=(int)($user['id']??0);
        $readerTagStmt->execute($readerTagParams);
        foreach($readerTagStmt->fetchAll() as $readerTag)$readerTags[(int)$readerTag['mail_account_id']][]=['id'=>(int)$readerTag['id'],'name'=>(string)$readerTag['name'],'color'=>(string)$readerTag['color']];
        $autoReplyAccountIds = array_map('intval', array_column($accounts, 'id'));
        $activeAutoReplyStmt = $pdo->prepare('SELECT 1 FROM sodium_auto_reply_rules r
            INNER JOIN sodium_auto_reply_rule_accounts ra ON ra.rule_id=r.id
            WHERE r.enabled=1 AND ra.mail_account_id IN (' . implode(',', array_fill(0, count($autoReplyAccountIds), '?')) . ')
              AND (r.starts_at IS NULL OR r.starts_at<=NOW())
              AND (r.ends_at IS NULL OR r.ends_at>=NOW()) LIMIT 1');
        $activeAutoReplyStmt->execute($autoReplyAccountIds);
        $hasActiveAutoReply = (bool)$activeAutoReplyStmt->fetchColumn();
    }
    if (sodium_can('sodium_signatures_view') && $accounts) {
        $footerAccountIds = array_map('intval', array_column($accounts, 'id'));
        $signatureStmt = $pdo->prepare('SELECT id,mail_account_id,name,sender_name,
            IF(user_id=?,is_default,0) is_default,content_html
            FROM sodium_signatures
            WHERE mail_account_id IN (' . implode(',', array_fill(0, count($footerAccountIds), '?')) . ')
              AND (user_id=? OR is_shared=1)
            ORDER BY is_default DESC,name');
        $signatureStmt->execute(array_merge([(int)($user['id'] ?? 0)], $footerAccountIds, [(int)($user['id'] ?? 0)]));
        $signatures = $signatureStmt->fetchAll();
    }
    if (sodium_can('sodium_templates_view') && $accounts) {
        $templateAccountIds = array_map('intval', array_column($accounts, 'id'));
        $templateStmt = $pdo->prepare('SELECT id,mail_account_id,name,subject,content_html FROM sodium_reply_templates
            WHERE (mail_account_id IS NULL OR mail_account_id IN (' . implode(',', array_fill(0, count($templateAccountIds), '?')) . '))
              AND (user_id=? OR is_shared=1) ORDER BY name');
        $templateStmt->execute(array_merge($templateAccountIds, [(int)($user['id'] ?? 0)]));
        $replyTemplates = $templateStmt->fetchAll();
    }
    $requestedDraftId = (int)($_GET['compose_draft'] ?? 0);
    if ($requestedDraftId && $accounts) {
        $draftAccountIds = array_map('intval', array_column($accounts, 'id'));
        $draftStmt = $pdo->prepare("SELECT * FROM sodium_composed_messages WHERE id=? AND user_id=? AND status IN ('draft','failed') AND mail_account_id IN (".implode(',',array_fill(0,count($draftAccountIds),'?')).')');
        $draftStmt->execute(array_merge([$requestedDraftId,(int)($user['id']??0)],$draftAccountIds));
        $composeDraft = $draftStmt->fetch() ?: null;
    }
    ?>
            </section>
        </main>
        <?php if ($accounts): ?>
        <div class="modal fade" id="messageReaderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable reader-dialog"><div class="modal-content">
                <div class="modal-header reader-header"><div class="reader-avatar"><i class="bi bi-person"></i></div><div class="min-width-0 flex-grow-1"><h2 class="modal-title h5 text-truncate mb-1" id="readerSubject">Message</h2><div class="reader-sender text-truncate" id="readerSender"></div><div class="small text-muted text-truncate" id="readerReplyTo"></div><div class="small text-muted text-truncate" id="readerMeta"></div></div><span id="readerTags" class="d-flex gap-1 flex-wrap"></span><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body reader-modal-body"><div class="reader-loading text-center py-5" id="readerLoading"><div class="spinner-border text-danger"></div></div><div class="reader-security d-none" id="readerSecurity"><div><i class="bi bi-shield-lock-fill"></i><strong> Votre confidentialité est protégée</strong><span> Les images distantes ont été bloquées pour empêcher le suivi de lecture.</span></div><div class="reader-security-actions"><button class="btn btn-sm btn-outline-primary" type="button" id="readerShowImages">Charger les images</button><button class="btn btn-sm btn-link" type="button" id="readerAlwaysImages">Toujours charger les images de cet expéditeur</button></div></div><div class="reader-surface"><iframe id="readerBody" class="reader-body d-none" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" title="Contenu du message"></iframe></div><div id="readerReplies" class="d-none mt-3"></div><div id="readerAttachments" class="reader-attachments"></div><div id="readerAttachmentPreview" class="reader-attachment-preview d-none"><div class="reader-attachment-preview-header"><div><i class="bi bi-eye me-2"></i><strong id="readerAttachmentPreviewName">Aperçu</strong></div><button class="btn btn-sm btn-outline-secondary" type="button" id="readerAttachmentPreviewClose"><i class="bi bi-x-lg"></i> Fermer</button></div><div class="reader-attachment-preview-body" id="readerAttachmentPreviewBody"></div></div></div>
                <div class="modal-footer reader-footer"><div class="reader-footer-primary"><button class="btn btn-success" type="button" id="readerReply"><i class="bi bi-reply"></i> <span>Répondre</span></button><button class="btn btn-outline-success reader-icon-action" type="button" id="readerReplyAll" title="Répondre à tous" aria-label="Répondre à tous"><i class="bi bi-reply-all"></i></button><button class="btn btn-primary reader-icon-action" type="button" id="readerForward" title="Transférer" aria-label="Transférer"><i class="bi bi-forward"></i></button><button class="btn btn-outline-secondary reader-icon-action" type="button" data-reader-action="archive" title="Archiver" aria-label="Archiver"><i class="bi bi-archive"></i></button><button class="btn btn-outline-warning reader-icon-action" type="button" data-reader-action="junk" title="Indésirable" aria-label="Indésirable"><i class="bi bi-exclamation-octagon"></i></button><button class="btn btn-outline-danger reader-icon-action" type="button" data-reader-action="trash" title="Supprimer" aria-label="Supprimer"><i class="bi bi-trash"></i></button></div><div class="reader-footer-actions"><div class="input-group reader-action-select"><select class="form-select" id="readerMoveFolder" aria-label="Dossier de destination"><option value="">Déplacer vers…</option></select><button class="btn btn-outline-primary" type="button" data-reader-select-action="move" title="Déplacer"><i class="bi bi-folder-symlink"></i></button></div><div class="input-group reader-action-select"><select class="form-select" id="readerAddTag" aria-label="Tag à ajouter"><option value="">Ajouter un tag…</option></select><button class="btn btn-outline-secondary" type="button" data-reader-select-action="tag" title="Ajouter le tag"><i class="bi bi-tag"></i></button></div></div><button class="btn btn-outline-secondary reader-close-action ms-auto" type="button" data-bs-dismiss="modal">Fermer</button></div>
            </div></div>
        </div>
        <div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog compose-dialog modal-dialog-scrollable"><div class="modal-content"><form method="post" action="/send.php" enctype="multipart/form-data">
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/index.php') ?>">
                <input type="hidden" name="compose_id" value="<?= (int)($composeDraft['id'] ?? 0) ?>">
                <input type="hidden" name="reply_account_id" value="">
                <input type="hidden" name="reply_message_key" value="">
                <input type="hidden" name="reply_message_id" value="">
                <input type="hidden" name="forward_account_id" value="">
                <input type="hidden" name="forward_folder" value="">
                <input type="hidden" name="forward_uid" value="">
                <div class="modal-header"><h2 class="modal-title h5"><i class="bi bi-pencil-square me-2"></i>Nouveau message</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><div class="row g-0 compose-layout"><div class="col-xl-9 compose-main"><div class="row g-3">
                    <div class="col-md-5"><label class="form-label">Expéditeur</label><select class="form-select" name="mail_account_id" id="composeAccount" required><?php foreach ($accounts as $account): ?><?php if (!empty($account['can_send'])): ?><option value="<?= (int)$account['id'] ?>" <?= $defaultComposeAccountId===(int)$account['id']?'selected':'' ?>><?= e($account['display_name'] ?: $account['email_address']) ?> — <?= e($account['email_address']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Signature</label><select class="form-select" name="signature_id" id="composeSignature"><option value="">Signature du profil</option><?php foreach ($signatures as $signature): ?><option value="<?= (int)$signature['id'] ?>" data-account="<?= (int)$signature['mail_account_id'] ?>" data-content="<?= e($signature['content_html']) ?>" <?= $signature['is_default']?'data-default="1"':'' ?>><?= e($signature['name']) ?> — <?= e($signature['sender_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Priorité</label><select class="form-select" name="priority"><option value="normal">Normale</option><option value="high">Haute</option><option value="low">Basse</option></select></div>
                    <?php if ($replyTemplates): ?><div class="col-12"><label class="form-label">Modèle de réponse</label><select class="form-select" id="composeTemplate"><option value="">Choisir un modèle…</option><?php foreach($replyTemplates as $template): ?><option value="<?= (int)$template['id'] ?>" data-account="<?= (int)($template['mail_account_id'] ?? 0) ?>" data-subject="<?= e($template['subject']) ?>" data-content="<?= e($template['content_html']) ?>"><?= e($template['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                    <div class="col-12"><label class="form-label">À</label><div class="recipient-field" data-recipient-field data-required="1"><div class="recipient-chips"></div><input class="recipient-input" type="text" placeholder="Nom, prénom ou adresse mail" autocomplete="off"><input type="hidden" name="to_email"><div class="recipient-suggestions"></div></div></div>
                    <div class="col-md-6"><label class="form-label">Cc</label><div class="recipient-field" data-recipient-field><div class="recipient-chips"></div><input class="recipient-input" type="text" placeholder="Ajouter des destinataires" autocomplete="off"><input type="hidden" name="cc_email"><div class="recipient-suggestions"></div></div></div>
                    <div class="col-md-6"><label class="form-label">Cci</label><div class="recipient-field" data-recipient-field><div class="recipient-chips"></div><input class="recipient-input" type="text" placeholder="Ajouter des destinataires masqués" autocomplete="off"><input type="hidden" name="bcc_email"><div class="recipient-suggestions"></div></div></div>
                    <div class="col-12"><label class="form-label">Objet</label><input class="form-control" name="subject" required></div>
                    <div class="col-12"><label class="form-label">Message</label><div class="rich-editor"><div class="rich-editor-toolbar"><button type="button" data-command="undo" title="Annuler"><i class="bi bi-arrow-counterclockwise"></i></button><button type="button" data-command="redo" title="Rétablir"><i class="bi bi-arrow-clockwise"></i></button><span></span><button type="button" data-command="formatBlock" data-value="p" title="Paragraphe">P</button><button type="button" data-command="formatBlock" data-value="h2" title="Titre">H</button><button type="button" data-command="bold" title="Gras"><i class="bi bi-type-bold"></i></button><button type="button" data-command="italic" title="Italique"><i class="bi bi-type-italic"></i></button><button type="button" data-command="underline" title="Souligné"><i class="bi bi-type-underline"></i></button><button type="button" data-command="strikeThrough" title="Barré"><i class="bi bi-type-strikethrough"></i></button><span></span><button type="button" data-command="justifyLeft"><i class="bi bi-text-left"></i></button><button type="button" data-command="justifyCenter"><i class="bi bi-text-center"></i></button><button type="button" data-command="justifyRight"><i class="bi bi-text-right"></i></button><button type="button" data-command="outdent" title="Diminuer le retrait"><i class="bi bi-text-indent-right"></i></button><button type="button" data-command="indent" title="Augmenter le retrait"><i class="bi bi-text-indent-left"></i></button><button type="button" data-command="insertUnorderedList" title="Liste"><i class="bi bi-list-ul"></i></button><button type="button" data-command="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button><button type="button" data-command="formatBlock" data-value="blockquote" title="Citation"><i class="bi bi-quote"></i></button><button type="button" data-command="createLink" title="Lien"><i class="bi bi-link-45deg"></i></button><button type="button" data-command="removeFormat" title="Effacer la mise en forme"><i class="bi bi-eraser"></i></button><label class="editor-color" title="Couleur du texte"><input type="color" data-editor-color value="#1f2937"></label></div><div class="rich-editor-content" contenteditable="true" data-placeholder="Rédigez votre message…"></div><textarea class="d-none" name="content"></textarea></div></div>
                </div></div><aside class="col-xl-3 compose-attachments"><h3 class="h6"><i class="bi bi-paperclip"></i> Pièces jointes</h3><label class="attachment-dropzone"><i class="bi bi-cloud-arrow-up"></i><span>Ajouter des fichiers</span><small>25 Mo maximum au total</small><input type="file" name="attachments[]" multiple></label><div class="forwarded-attachment-list"></div><div class="attachment-file-list"></div></aside></div></div>
                <div class="modal-footer compose-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-outline-secondary" type="submit" name="compose_action" value="draft"><i class="bi bi-file-earmark-text"></i> Enregistrer (brouillon)</button><div class="compose-schedule"><input class="form-control form-control-sm" type="datetime-local" name="scheduled_at" aria-label="Date et heure d’envoi"><button class="btn btn-outline-primary text-nowrap" type="submit" name="compose_action" value="schedule"><i class="bi bi-clock"></i> Programmer l’envoi</button></div><button class="btn btn-danger" type="submit" name="compose_action" value="send"><i class="bi bi-send"></i> Envoyer maintenant</button></div>
            </form></div></div>
        </div>
        <div class="modal fade" id="composeLeaveModal" tabindex="-1" aria-hidden="true" aria-labelledby="composeLeaveTitle">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h2 class="modal-title h5" id="composeLeaveTitle"><i class="bi bi-file-earmark-text me-2"></i>Conserver ce message ?</h2></div>
                <div class="modal-body"><p class="mb-0">Voulez-vous enregistrer cette rédaction dans les brouillons avant de la quitter ?</p></div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-compose-leave="continue">Continuer la rédaction</button><button class="btn btn-outline-danger" type="button" data-compose-leave="discard"><i class="bi bi-trash"></i> Quitter sans enregistrer</button><button class="btn btn-danger" type="button" data-compose-leave="draft"><i class="bi bi-file-earmark-check"></i> Enregistrer en brouillon</button></div>
            </div></div>
        </div>
        <?php endif; ?>
        <script src="<?=e($bootstrapJs)?>" <?=$remoteDependencies?'onerror="this.onerror=null;this.src=\'/assets/vendor/bootstrap/bootstrap.bundle.min.js\'"':''?>></script>
        <script src="/js/i18n.js?v=20260819-01"></script>
        <script>
            (() => {
                const root = document.documentElement;
                <?php if($hasActiveAutoReply): ?>
                (() => {
                    const storageKey = 'sodiumAutoReplyNoticeDismissedAt';
                    const dismissedAt = Number(localStorage.getItem(storageKey) || 0);
                    if (Date.now() - dismissedAt < 86400000) return;
                    const notice = document.createElement('div');
                    notice.className = 'alert alert-warning d-flex align-items-start gap-3 mb-3';
                    notice.setAttribute('role', 'status');
                    notice.innerHTML = '<i class="bi bi-reply-all-fill fs-5" aria-hidden="true"></i><div class="flex-grow-1"><strong>Réponse automatique active</strong><div>Une ou plusieurs règles de réponse automatique sont actuellement actives sur vos comptes mails. <a class="alert-link" href="/messages.php">Consulter les paramètres de messages</a>.</div></div><button class="btn-close" type="button" aria-label="Masquer cette information pendant 24 heures"></button>';
                    notice.querySelector('.btn-close')?.addEventListener('click', () => {
                        localStorage.setItem(storageKey, String(Date.now()));
                        notice.remove();
                    });
                    document.querySelector('.content')?.prepend(notice);
                })();
                <?php endif; ?>
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const readerFolders = <?= json_encode($readerFolders, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
                const readerTags = <?= json_encode($readerTags, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
                const hasMailAccounts = <?= $accounts ? 'true' : 'false' ?>;
                const scheduledEditId = <?= (int)((!empty($composeDraft['edit_original_scheduled_at'])) ? $composeDraft['id'] : 0) ?>;
                let composeSubmissionInProgress = false;
                let scheduledEditCancelled = false;
                const cancelScheduledEdit = async useBeacon => {
                    if (!scheduledEditId || composeSubmissionInProgress || scheduledEditCancelled) return;
                    scheduledEditCancelled = true;
                    const data = new FormData();data.set('id',String(scheduledEditId));data.set('action','cancel_edit');data.set('_csrf',csrfToken);
                    if(useBeacon&&navigator.sendBeacon){navigator.sendBeacon('/composed-action.php',data);return;}
                    try{
                        const response=await fetch('/composed-action.php',{method:'POST',headers:{Accept:'application/json'},body:data});
                        const result=await response.json();
                        showClientToast(result.restored?'Programmation initiale restaurée.':'La date initiale est dépassée : le message reste en brouillon.',result.restored?'success':'warning');
                        window.setTimeout(()=>window.location.reload(),500);
                    }catch(error){showClientToast('Impossible de restaurer la programmation initiale.','danger');}
                };
                window.addEventListener('beforeunload',()=>cancelScheduledEdit(true));
                const nativeFetch = window.fetch.bind(window);
                window.fetch = async (resource, options = {}) => {
                    const method = String(options.method || 'GET').toUpperCase();
                    const headers = new Headers(options.headers || {});
                    if (method === 'POST' && csrfToken) headers.set('X-CSRF-Token', csrfToken);
                    const response=await nativeFetch(resource,{...options,headers});
                    if(response.status===401||response.status===419){let target='/login.php?expired=1';try{const payload=await response.clone().json();if(payload.redirect)target=payload.redirect;}catch(error){}window.location.assign(target);throw new Error('SESSION_EXPIRED');}
                    return response;
                };
                document.querySelectorAll('.modal').forEach(modal => modal.setAttribute('data-bs-backdrop', 'static'));
                const sidebarToggle = document.getElementById('sidebarToggle');
                const mobileToggle = document.getElementById('mobileMenuToggle');
                const backdrop = document.getElementById('sidebarBackdrop');
                const mobileQuery = window.matchMedia('(max-width: 900px)');

                const closeMobileMenu = () => {
                    root.classList.remove('mobile-menu-open');
                    document.body.classList.remove('mobile-menu-lock');
                    mobileToggle?.setAttribute('aria-expanded', 'false');
                };

                sidebarToggle?.addEventListener('click', () => {
                    if(window.matchMedia('(max-width: 575.98px)').matches)return;
                    root.classList.toggle('sidebar-collapsed');
                    try {
                        localStorage.setItem('sodiumSidebarCollapsed', root.classList.contains('sidebar-collapsed') ? '1' : '0');
                    } catch (error) {}
                });
                mobileToggle?.addEventListener('click', () => {
                    const open = !root.classList.contains('mobile-menu-open');
                    root.classList.toggle('mobile-menu-open', open);
                    document.body.classList.toggle('mobile-menu-lock', open);
                    mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                backdrop?.addEventListener('click', closeMobileMenu);
                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape') closeMobileMenu();
                    const target = event.target;
                    const editing = target instanceof HTMLElement && (target.matches('input,textarea,select') || target.isContentEditable);
                    if (editing || event.ctrlKey || event.metaKey || event.altKey) return;
                    if (event.key === '/' ) {
                        const search = document.querySelector('.mail-search input[type="search"]');
                        if (search) { event.preventDefault(); search.focus(); }
                    }
                    if (event.key.toLowerCase() === 'c') {
                        const compose = document.getElementById('composeModal');
                        if (compose) { event.preventDefault(); bootstrap.Modal.getOrCreateInstance(compose).show(); }
                    }
                    if (event.key.toLowerCase() === 'r' && document.getElementById('messageReaderModal')?.classList.contains('show')) {
                        event.preventDefault();
                        document.getElementById('readerReply')?.click();
                    }
                });
                mobileQuery.addEventListener?.('change', closeMobileMenu);
                document.querySelectorAll('.toast').forEach(element => bootstrap.Toast.getOrCreateInstance(element).show());
                const showClientToast = (message, type = 'warning') => {
                    const container = document.getElementById('appToastContainer');
                    if (!container) return;
                    const element = document.createElement('div');
                    element.className = `toast border-0 text-bg-${type}`;
                    element.innerHTML = `<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close ${['danger','success'].includes(type)?'btn-close-white':''} me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
                    element.querySelector('.toast-body').textContent = message;
                    container.appendChild(element);
                    bootstrap.Toast.getOrCreateInstance(element, {delay: type === 'danger' ? 10000 : 5000}).show();
                    element.addEventListener('hidden.bs.toast', () => element.remove());
                };
                const defaultDocumentTitle = <?= json_encode($sodiumDefaultBrowserTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                const updateDocumentTitle = unread => {
                    const count = Math.max(0, Number(unread || 0));
                    document.title = count > 0
                        ? `${count} nouveau${count > 1 ? 'x' : ''} message${count > 1 ? 's' : ''} - Sodium`
                        : defaultDocumentTitle;
                };
                const updateUnreadBadges = status => {
                    if (!status) return;
                    updateDocumentTitle(status.unified_unread);
                    const unifiedBadge = document.querySelector('[data-unified-unread]');
                    if (unifiedBadge) {
                        unifiedBadge.textContent = String(status.unified_unread || 0);
                        unifiedBadge.classList.toggle('d-none', Number(status.unified_unread || 0) < 1);
                    }
                    (status.accounts || []).forEach(account => {
                        document.querySelectorAll(`[data-account-unread="${account.id}"], [data-active-inbox-unread="${account.id}"]`).forEach(badge => {
                            badge.textContent = String(account.unread || 0);
                            badge.classList.toggle('d-none', Number(account.unread || 0) < 1);
                        });
                    });
                    const outboxBadge=document.querySelector('[data-outbox-count]');if(outboxBadge){outboxBadge.textContent=String(status.outbox_count||0);outboxBadge.classList.toggle('d-none',Number(status.outbox_count||0)<1);}
                };

                const composeAccount = document.getElementById('composeAccount');
                const composeSignature = document.getElementById('composeSignature');
                const composeTemplate = document.getElementById('composeTemplate');
                const quoteReplyEnabled = <?= !empty($sodiumSettings['quote_reply']) ? 'true' : 'false' ?>;
                const signaturePosition = <?= json_encode((string)$sodiumSettings['signature_position'], JSON_UNESCAPED_UNICODE) ?>;
                const applyComposeSignature = () => {
                    const editor = document.querySelector('#composeModal .rich-editor-content');
                    if (!editor || !composeSignature) return;
                    editor.querySelectorAll('[data-sodium-signature-spacer]').forEach(spacer => {
                        const meaningful=spacer.innerHTML.replace(/<br\s*\/?>|&nbsp;|\s/gi,'');
                        if(meaningful){const preserved=document.createElement('p');preserved.innerHTML=spacer.innerHTML;spacer.before(preserved);}
                        spacer.remove();
                    });
                    editor.querySelectorAll('[data-sodium-signature]').forEach(element => element.remove());
                    const option = composeSignature.selectedOptions[0];
                    if (!option?.value || !option.dataset.content) return;
                    const signature = document.createElement('div');
                    signature.className = 'signature';
                    signature.dataset.sodiumSignature = option.value;
                    signature.style.marginTop = '2em';
                    signature.innerHTML = option.dataset.content;
                    const quote = editor.querySelector('[data-sodium-quote]');
                    if (quote && signaturePosition === 'before_quote') quote.before(signature);
                    else editor.append(signature);
                };
                const syncSignatures = () => {
                    if (!composeAccount || !composeSignature) return;
                    let selectedDefault = null;
                    Array.from(composeSignature.options).forEach(option => {
                        const visible = !option.value || option.dataset.account === composeAccount.value;
                        option.hidden = !visible;
                        option.disabled = !visible;
                        if (visible && option.dataset.default === '1') selectedDefault = option;
                    });
                    composeSignature.value = selectedDefault?.value || '';
                    applyComposeSignature();
                };
                composeAccount?.addEventListener('change', syncSignatures);
                composeSignature?.addEventListener('change', applyComposeSignature);
                syncSignatures();
                const focusComposeEditor = () => {
                    const editor = document.querySelector('#composeModal .rich-editor-content');
                    if (!editor) return;
                    let target = Array.from(editor.children).find(element => !element.matches('[data-sodium-signature],[data-sodium-signature-spacer],[data-sodium-quote]'));
                    if(!target){target=document.createElement('p');target.innerHTML='<br>';const anchor=editor.querySelector('[data-sodium-signature],[data-sodium-quote]');anchor?anchor.before(target):editor.prepend(target);}
                    editor.focus();
                    const selection = window.getSelection();
                    if (!selection) return;
                    const range = document.createRange();
                    range.selectNodeContents(target);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                };
                document.getElementById('composeModal')?.addEventListener('shown.bs.modal', () => {
                    window.requestAnimationFrame(focusComposeEditor);
                });
                composeTemplate?.addEventListener('change', () => {
                    const option = composeTemplate.selectedOptions[0];
                    if (!option?.value) return;
                    const editor = document.querySelector('#composeModal .rich-editor-content');
                    const subject = document.querySelector('#composeModal [name="subject"]');
                    if (subject && option.dataset.subject && !subject.value.trim()) subject.value = option.dataset.subject;
                    if (editor && option.dataset.content) {
                        const signature = editor.querySelector('[data-sodium-signature]');
                        const fragment = document.createElement('div');
                        fragment.dataset.sodiumTemplate = option.value;
                        fragment.innerHTML = option.dataset.content;
                        signature ? signature.before(fragment) : editor.append(fragment);
                    }
                    composeTemplate.value = '';
                });
                document.querySelectorAll('[data-compose-account]').forEach(button => button.addEventListener('click', () => {
                    if (!composeAccount) return;
                    composeAccount.value = button.dataset.composeAccount;
                    composeAccount.dispatchEvent(new Event('change'));
                }));

                document.querySelectorAll('[data-recipient-field]').forEach(field => {
                    const input = field.querySelector('.recipient-input');
                    const hidden = field.querySelector('input[type="hidden"]');
                    const chips = field.querySelector('.recipient-chips');
                    const suggestions = field.querySelector('.recipient-suggestions');
                    const values = new Map();
                    let timer;
                    const sync = () => hidden.value = Array.from(values.keys()).join(',');
                    const add = (email, name = '') => {
                        email = email.trim().toLowerCase();
                        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || values.has(email)) return;
                        values.set(email, name);
                        const chip = document.createElement('span');
                        chip.className = 'recipient-chip';
                        const label = document.createElement('span');
                        label.textContent = (name ? name + ' · ' : '') + email;
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.setAttribute('aria-label', 'Retirer');
                        remove.textContent = '×';
                        remove.addEventListener('click', () => { values.delete(email); chip.remove(); sync(); });
                        chip.append(label, remove);
                        chips.appendChild(chip);
                        input.value = '';
                        suggestions.innerHTML = '';
                        sync();
                    };
                    field.addEventListener('recipient:add', event => add(event.detail.email, event.detail.name || ''));
                    field.addEventListener('recipient:reset', () => {
                        values.clear();
                        chips.innerHTML = '';
                        suggestions.innerHTML = '';
                        input.value = '';
                        sync();
                    });
                    input.addEventListener('keydown', event => {
                        if (['Enter', ',', ';'].includes(event.key)) {
                            event.preventDefault();
                            add(input.value);
                        } else if (event.key === 'Backspace' && !input.value && values.size) {
                            const last = Array.from(values.keys()).pop();
                            values.delete(last);
                            chips.lastElementChild?.remove();
                            sync();
                        }
                    });
                    input.addEventListener('blur', () => { if (input.value.includes('@')) add(input.value); setTimeout(() => suggestions.innerHTML = '', 180); });
                    input.addEventListener('input', () => {
                        clearTimeout(timer);
                        const q = input.value.trim();
                        if (!q) { suggestions.innerHTML = ''; return; }
                        timer = setTimeout(async () => {
                            try {
                                const response = await fetch('/api/contacts.php?q=' + encodeURIComponent(q) + '&account_id=' + encodeURIComponent(composeAccount?.value || ''));
                                const contacts = await response.json();
                                suggestions.innerHTML = '';
                                contacts.forEach(contact => {
                                    const button = document.createElement('button');
                                    button.type = 'button';
                                    const strong = document.createElement('strong');
                                    const small = document.createElement('small');
                                    strong.textContent = contact.name;
                                    small.textContent = contact.email;
                                    button.append(strong, small);
                                    button.addEventListener('mousedown', event => { event.preventDefault(); add(contact.email, contact.name); });
                                    suggestions.appendChild(button);
                                });
                            } catch (error) {}
                        }, 180);
                    });
                });
                <?php if($composeDraft): ?>
                const loadedDraft = <?= json_encode([
                    'account_id'=>(int)$composeDraft['mail_account_id'],
                    'to'=>json_decode((string)$composeDraft['to_json'],true)?:[],
                    'cc'=>json_decode((string)$composeDraft['cc_json'],true)?:[],
                    'bcc'=>json_decode((string)$composeDraft['bcc_json'],true)?:[],
                    'subject'=>(string)$composeDraft['subject'],
                    'content'=>(string)$composeDraft['content_html'],
                    'signature_id'=>(int)($composeDraft['signature_id']??0),
                    'priority'=>(string)$composeDraft['priority'],
                    'original_scheduled_at'=>!empty($composeDraft['edit_original_scheduled_at'])?sodium_format_date($composeDraft['edit_original_scheduled_at'],'Y-m-d\TH:i'):'',
                ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
                if (composeAccount) {
                    composeAccount.value=String(loadedDraft.account_id);
                    composeAccount.dispatchEvent(new Event('change'));
                    const draftForm=document.querySelector('#composeModal form');
                    const recipientNames={to:'to_email',cc:'cc_email',bcc:'bcc_email'};
                    Object.entries(recipientNames).forEach(([key,name])=>{
                        const field=draftForm?.querySelector(`[name="${name}"]`)?.closest('[data-recipient-field]');
                        (loadedDraft[key]||[]).forEach(email=>field?.dispatchEvent(new CustomEvent('recipient:add',{detail:{email}})));
                    });
                    const subject=draftForm?.querySelector('[name="subject"]'); if(subject)subject.value=loadedDraft.subject;
                    const priority=draftForm?.querySelector('[name="priority"]'); if(priority)priority.value=loadedDraft.priority;
                    const scheduledAt=draftForm?.querySelector('[name="scheduled_at"]'); if(scheduledAt&&loadedDraft.original_scheduled_at)scheduledAt.value=loadedDraft.original_scheduled_at;
                    const editor=draftForm?.querySelector('.rich-editor-content'); if(editor)editor.innerHTML=loadedDraft.content;
                    if(composeSignature&&loadedDraft.signature_id){composeSignature.value=String(loadedDraft.signature_id);}
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('composeModal')).show();
                }
                <?php endif; ?>

                document.querySelectorAll('.rich-editor').forEach(editor => {
                    const content = editor.querySelector('.rich-editor-content');
                    const textarea = editor.querySelector('textarea');
                    if (textarea.value.trim()) content.innerHTML = textarea.value;
                    editor.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => {
                        const command = button.dataset.command;
                        const value = command === 'createLink' ? prompt('Adresse du lien') : (button.dataset.value || null);
                        if (command !== 'createLink' || value) document.execCommand(command, false, value);
                        content.focus();
                    }));
                    editor.querySelector('[data-editor-color]')?.addEventListener('input', event => {
                        document.execCommand('foreColor', false, event.target.value);
                        content.focus();
                    });
                    editor.closest('form')?.addEventListener('submit', event => {
                        textarea.value = content.innerHTML.trim();
                        const requiredRecipients = editor.closest('form').querySelector('[data-recipient-field][data-required="1"] input[type="hidden"]');
                        const composeAction = event.submitter?.value || 'send';
                        if (composeAction !== 'draft' && (!textarea.value || (requiredRecipients && !requiredRecipients.value))) {
                            event.preventDefault();
                            showClientToast(requiredRecipients ? 'Ajoutez un destinataire et rédigez votre message.' : 'Rédigez le contenu de la signature.', 'warning');
                            return;
                        }
                        if (composeAction === 'schedule' && !editor.closest('form').querySelector('[name="scheduled_at"]')?.value) {
                            event.preventDefault();
                            showClientToast('Choisissez la date et l’heure de l’envoi.', 'warning');
                            return;
                        }
                        composeSubmissionInProgress = true;
                    });
                });

                const formatHumanDate = timestamp => {
                    if (!timestamp) return '';
                    const now = Math.floor(Date.now() / 1000);
                    if (timestamp > now + 30) {
                        const future = timestamp - now;
                        if (future < 3600) return `dans ${Math.max(1, Math.ceil(future / 60))} min`;
                        if (future < 86400) return `dans ${Math.max(1, Math.ceil(future / 3600))} h`;
                        return `dans ${Math.max(1, Math.ceil(future / 86400))} j`;
                    }
                    const difference = Math.max(0, now - timestamp);
                    if (difference < 60) return 'à l’instant';
                    if (difference < 3600) return `il y a ${Math.max(1, Math.floor(difference / 60))} min`;
                    if (difference < 86400) return `il y a ${Math.floor(difference / 3600)} h`;
                    if (difference < 172800) return 'hier';
                    if (difference < 604800) return `il y a ${Math.floor(difference / 86400)} jours`;
                    return new Date(timestamp * 1000).toLocaleDateString('fr-FR');
                };
                const refreshHumanDates = () => document.querySelectorAll('.human-date').forEach(date => {
                    const timestamp = Number(date.dataset.timestamp || 0);
                    if (!timestamp) return;
                    date.dataset.human = formatHumanDate(timestamp);
                    if (date.dataset.dateMode !== 'exact') date.textContent = date.dataset.human;
                });
                const bindHumanDate = date => {
                    if(date.dataset.humanDateBound==='1')return;
                    date.dataset.humanDateBound='1';
                    const toggle = () => {
                        const showHuman = date.dataset.dateMode === 'exact';
                        date.dataset.dateMode = showHuman ? 'human' : 'exact';
                        date.textContent = showHuman ? date.dataset.human : date.dataset.exact;
                    };
                    date.addEventListener('click', event => { event.stopPropagation(); toggle(); });
                    date.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); event.stopPropagation(); toggle(); } });
                };
                document.querySelectorAll('.human-date').forEach(bindHumanDate);
                refreshHumanDates();
                if (document.querySelector('.human-date')) window.setInterval(refreshHumanDates, 30000);

                document.querySelectorAll('.bulk-mail-form').forEach(form => {
                    const master = form.querySelector('.select-all-messages');
                    const actions = form.querySelector('.bulk-buttons');
                    const sync = () => {
                        const checks = Array.from(form.querySelectorAll('.message-checkbox'));
                        const selected = checks.filter(check => check.checked).length;
                        actions?.classList.toggle('visible', selected > 0);
                        if (master) {
                            master.checked = selected === checks.length && checks.length > 0;
                            master.indeterminate = selected > 0 && selected < checks.length;
                        }
                    };
                    master?.addEventListener('change', () => { form.querySelectorAll('.message-checkbox').forEach(check => check.checked = master.checked); sync(); });
                    form.addEventListener('change',event=>{if(event.target.matches('.message-checkbox'))sync();});
                    sync();
                });

                const readerElement = document.getElementById('messageReaderModal');
                const readerModal = readerElement ? bootstrap.Modal.getOrCreateInstance(readerElement) : null;
                let readerMessage = null;
                let attachmentPreviewObjectUrl = '';
                const closeAttachmentPreview = () => {
                    const preview = document.getElementById('readerAttachmentPreview');
                    const body = document.getElementById('readerAttachmentPreviewBody');
                    preview?.classList.add('d-none');
                    if (body) body.innerHTML = '';
                    if (attachmentPreviewObjectUrl) {
                        URL.revokeObjectURL(attachmentPreviewObjectUrl);
                        attachmentPreviewObjectUrl = '';
                    }
                };
                document.getElementById('readerAttachmentPreviewClose')?.addEventListener('click', closeAttachmentPreview);
                const openMessage = async row => {
                    if (!readerModal) return;
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => bootstrap.Tooltip.getInstance(element)?.hide());
                    readerMessage = {account_id: row.dataset.account, folder: row.dataset.folder, uid: row.dataset.uid, selection: row.querySelector('.message-checkbox')?.value || ''};
                    document.getElementById('readerLoading').classList.remove('d-none');
                    const readerBody = document.getElementById('readerBody');
                    readerBody.classList.add('d-none');
                    readerBody.srcdoc = '';
                    const readerReplies = document.getElementById('readerReplies');
                    readerReplies.innerHTML = '';
                    readerReplies.classList.add('d-none');
                    document.getElementById('readerAttachments').innerHTML = '';
                    document.getElementById('readerReplyAll')?.classList.add('d-none');
                    closeAttachmentPreview();
                    document.getElementById('readerSecurity').classList.add('d-none');
                    readerModal.show();
                    try {
                        const response = await fetch(`/api/message.php?account_id=${encodeURIComponent(readerMessage.account_id)}&folder=${encodeURIComponent(readerMessage.folder)}&uid=${encodeURIComponent(readerMessage.uid)}`);
                        const message = await response.json();
                        if (!response.ok) throw new Error(message.error || 'Lecture impossible');
                        readerMessage = {...readerMessage, ...message, selection:readerMessage.selection};
                        const moveSelect=document.getElementById('readerMoveFolder');
                        const tagSelect=document.getElementById('readerAddTag');
                        if(moveSelect){moveSelect.innerHTML='<option value="">Déplacer vers…</option>';(readerFolders[String(message.account_id)]||[]).filter(folder=>folder.key!==message.folder).forEach(folder=>{const option=document.createElement('option');option.value=folder.key;option.textContent=folder.label;moveSelect.appendChild(option);});}
                        if(tagSelect){tagSelect.innerHTML='<option value="">Ajouter un tag…</option>';(readerTags[String(message.account_id)]||[]).forEach(tag=>{const option=document.createElement('option');option.value=String(tag.id);option.textContent=tag.name;tagSelect.appendChild(option);});}
                        const replyAllRecipients = new Set();
                        [message.reply_email, ...(message.to_addresses || []).map(address => address.email), ...(message.cc_addresses || []).map(address => address.email)]
                            .forEach(email => {
                                const normalized = String(email || '').trim().toLowerCase();
                                if (normalized && normalized !== String(message.account_email || '').toLowerCase()) replyAllRecipients.add(normalized);
                            });
                        document.getElementById('readerReplyAll')?.classList.toggle('d-none', replyAllRecipients.size <= 1);
                        updateUnreadBadges(message.unread_status);
                        document.getElementById('readerSubject').textContent = message.subject;
                        const renderCopyableAddress=(element,value,prefix='')=>{element.textContent='';if(prefix)element.append(document.createTextNode(prefix));const text=String(value||'');const email=text.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i)?.[0];if(!email){element.append(document.createTextNode(text));return;}const before=text.indexOf(email);element.append(document.createTextNode(text.slice(0,before)));const button=document.createElement('button');button.type='button';button.className='reader-email-copy';button.textContent=email;button.title=`Copier ${email}`;button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(email);showClientToast(`${email} est dans le presse-papiers.`,'info');}catch(error){showClientToast('La copie dans le presse-papiers est indisponible.','warning');}});element.append(button,document.createTextNode(text.slice(before+email.length)));};
                        const renderCopyableRecipients=(element,addresses,fallback)=>{element.textContent='À : ';const recipients=Array.isArray(addresses)&&addresses.length?addresses:[{name:'',email:fallback}];recipients.forEach((address,index)=>{if(index)element.append(document.createTextNode(', '));const holder=document.createElement('span');const email=String(address.email||'').trim();const name=String(address.name||'').trim();renderCopyableAddress(holder,name&&email?`${name} <${email}>`:(email||fallback));element.append(holder);});const details=[message.date,message.account_name].filter(Boolean).join(' · ');if(details)element.append(document.createTextNode(` · ${details}`));};
                        renderCopyableAddress(document.getElementById('readerSender'),message.from,'De : ');
                        renderCopyableAddress(document.getElementById('readerReplyTo'),message.reply_to || message.reply_email || message.from,'Répondre à : ');
                        renderCopyableRecipients(document.getElementById('readerMeta'),message.to_addresses,message.to || message.account_name);
                        const body = document.getElementById('readerBody');
                        const renderReaderHtml = html => {
                            body.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_blank"><style>html,body{margin:0;padding:0;background:#fff;color:#212529}body{padding:24px;font-family:Arial,sans-serif;overflow-wrap:anywhere}img{max-width:100%}table{max-width:100%}@media(max-width:700px){body{padding:14px}}</style></head><body>${html || ''}</body></html>`;
                        };
                        body.onload=()=>{try{body.style.height=Math.max(320,body.contentDocument.documentElement.scrollHeight,body.contentDocument.body.scrollHeight)+'px';}catch(error){}};
                        renderReaderHtml(message.html);
                        readerMessage.renderHtml = renderReaderHtml;
                        body.classList.remove('d-none');
                        const replyMessages = message.reply_messages || [];
                        if (replyMessages.length) {
                            replyMessages.forEach(reply => {
                                const frame = document.createElement('section');
                                frame.className = 'card border-primary-subtle overflow-hidden mb-3';
                                const header = document.createElement('div');
                                header.className = 'card-header bg-primary-subtle text-primary-emphasis fw-semibold';
                                const parsedDate = new Date(String(reply.date || '').replace(' ', 'T'));
                                const formattedDate = Number.isNaN(parsedDate.getTime())
                                    ? (reply.date || '')
                                    : parsedDate.toLocaleString('fr-FR', {dateStyle:'long',timeStyle:'short'});
                                header.textContent = `${reply.is_mine ? 'Vous avez répondu' : `${reply.author} a répondu`} le ${formattedDate}`;
                                let replyBody;
                                if(reply.html){
                                    replyBody=document.createElement('iframe');
                                    replyBody.className='reader-reply-frame';
                                    replyBody.setAttribute('sandbox','allow-same-origin allow-popups allow-popups-to-escape-sandbox');
                                    replyBody.title=`Réponse de ${reply.author || 'Sodium'}`;
                                    replyBody.srcdoc=`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_blank"><style>html,body{margin:0;padding:0;background:#fff;color:#212529}body{padding:20px;font-family:Arial,sans-serif;overflow-wrap:anywhere}img{max-width:100%}table{max-width:100%}</style></head><body>${reply.html}</body></html>`;
                                }else{
                                    replyBody=document.createElement('div');
                                    replyBody.className='card-body bg-white text-dark';
                                    replyBody.innerHTML='<p class="text-muted mb-0">Le contenu de cette réponse historique n’est pas disponible.</p>';
                                }
                                frame.append(header, replyBody);
                                readerReplies.appendChild(frame);
                            });
                            readerReplies.classList.remove('d-none');
                        }
                        document.getElementById('readerSecurity').classList.toggle('d-none', !message.remote_images_blocked);
                        const tags = document.getElementById('readerTags');
                        tags.innerHTML = '';
                        (message.tags || []).forEach(tag => {
                            const badge = document.createElement('span');
                            badge.className = 'mail-tag reader-mail-tag';
                            badge.dataset.tagId = String(tag.id);
                            badge.style.setProperty('--tag-color', tag.color);
                            const label=document.createElement('span');label.textContent=tag.name;
                            const remove=document.createElement('button');remove.type='button';remove.className='reader-tag-remove';remove.title=`Retirer le tag ${tag.name}`;remove.setAttribute('aria-label',remove.title);remove.innerHTML='<i class="bi bi-x"></i>';
                            remove.addEventListener('click',async event=>{
                                event.stopPropagation();remove.disabled=true;
                                const data=new FormData();data.set('account_id',String(message.account_id));data.set('message_key',String(message.message_key));data.set('tag_id',String(tag.id));
                                try{
                                    const response=await fetch('/api/message-tag.php',{method:'POST',body:data,headers:{Accept:'application/json'}});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'Suppression impossible.');
                                    badge.remove();readerMessage.tags=(readerMessage.tags||[]).filter(item=>Number(item.id)!==Number(tag.id));
                                    document.querySelectorAll(`[data-message-row][data-account="${CSS.escape(String(message.account_id))}"][data-folder="${CSS.escape(String(message.folder))}"][data-uid="${CSS.escape(String(message.uid))}"] [data-tag-id="${CSS.escape(String(tag.id))}"]`).forEach(element=>element.remove());
                                    showClientToast(`Le tag « ${tag.name} » a été retiré du message.`,'success');
                                }catch(error){remove.disabled=false;showClientToast(error.message||'Suppression du tag impossible.','danger');}
                            });
                            badge.append(label,remove);
                            tags.appendChild(badge);
                        });
                        const attachments = document.getElementById('readerAttachments');
                        const downloadableAttachments = (message.attachments || []).filter(attachment => !attachment.inline);
                        if (downloadableAttachments.length) {
                            const heading = document.createElement('div');
                            heading.className = 'reader-attachments-heading';
                            const title = document.createElement('strong');
                            title.innerHTML = `<i class="bi bi-paperclip me-1"></i> Pièces jointes (${downloadableAttachments.length})`;
                            heading.appendChild(title);
                            const downloadAll = document.createElement('a');
                            downloadAll.className = 'btn btn-sm btn-outline-secondary';
                            downloadAll.href = message.attachments_zip_url;
                            downloadAll.innerHTML = '<i class="bi bi-file-earmark-zip"></i> Tout télécharger';
                            heading.appendChild(downloadAll);
                            attachments.appendChild(heading);
                        }
                        downloadableAttachments.forEach(attachment => {
                            const item = document.createElement('div');
                            item.className = 'reader-attachment-item';
                            const identity = document.createElement('div');
                            identity.className = 'reader-attachment-identity';
                            const icon = document.createElement('i');
                            icon.className = attachment.mime === 'application/pdf' ? 'bi bi-file-earmark-pdf' : (attachment.mime || '').startsWith('image/') ? 'bi bi-file-earmark-image' : 'bi bi-file-earmark';
                            const copy = document.createElement('span');
                            const name = document.createElement('strong');
                            name.textContent = attachment.name;
                            const size = document.createElement('small');
                            size.textContent = attachment.size ? (attachment.size < 1048576 ? `${Math.max(1, Math.ceil(attachment.size / 1024))} Ko` : `${(attachment.size / 1048576).toFixed(1)} Mo`) : '';
                            copy.append(name, size);
                            identity.append(icon, copy);
                            const actions = document.createElement('div');
                            actions.className = 'btn-group btn-group-sm';
                            if (attachment.previewable) {
                                const previewButton = document.createElement('button');
                                previewButton.type = 'button';
                                previewButton.className = 'btn btn-outline-secondary';
                                previewButton.title = 'Voir';
                                previewButton.setAttribute('aria-label', `Voir ${attachment.name}`);
                                previewButton.innerHTML = '<i class="bi bi-eye"></i>';
                                previewButton.addEventListener('click', async () => {
                                    const preview = document.getElementById('readerAttachmentPreview');
                                    const previewBody = document.getElementById('readerAttachmentPreviewBody');
                                    document.getElementById('readerAttachmentPreviewName').textContent = attachment.name;
                                    previewBody.innerHTML = '';
                                    preview.classList.remove('d-none');
                                    const loading = document.createElement('div');
                                    loading.className = 'text-center';
                                    loading.innerHTML = '<div class="spinner-border text-danger mb-2"></div><div>Chargement de l’aperçu…</div>';
                                    previewBody.appendChild(loading);
                                    previewButton.disabled = true;
                                    try {
                                        const response = await fetch(attachment.preview_url, {credentials:'same-origin'});
                                        if (!response.ok) throw new Error((await response.text()) || 'Aperçu indisponible.');
                                        const blob = await response.blob();
                                        if (attachmentPreviewObjectUrl) URL.revokeObjectURL(attachmentPreviewObjectUrl);
                                        attachmentPreviewObjectUrl = URL.createObjectURL(blob);
                                        const media = document.createElement(attachment.mime === 'application/pdf' ? 'iframe' : 'img');
                                        media.src = attachmentPreviewObjectUrl;
                                        media.title = attachment.name;
                                        previewBody.replaceChildren(media);
                                    } catch (error) {
                                        closeAttachmentPreview();
                                        showClientToast(error.message || 'Aperçu indisponible.', 'danger');
                                    } finally {
                                        previewButton.disabled = false;
                                    }
                                });
                                actions.appendChild(previewButton);
                            }
                            const download = document.createElement('a');
                            download.className = 'btn btn-outline-secondary';
                            download.href = attachment.download_url;
                            download.title = 'Télécharger';
                            download.setAttribute('aria-label', `Télécharger ${attachment.name}`);
                            download.innerHTML = '<i class="bi bi-download"></i>';
                            actions.appendChild(download);
                            item.append(identity, actions);
                            attachments.appendChild(item);
                        });
                        row.classList.remove('unread');
                        row.querySelector('.read-dot')?.classList.remove('is-unread');
                    } catch (error) {
                        showClientToast(error.message, 'danger');
                        readerModal.hide();
                    } finally {
                        document.getElementById('readerLoading').classList.add('d-none');
                    }
                };
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => bootstrap.Tooltip.getOrCreateInstance(element));
                let draggedMessageRow=null;
                let draggedMessagePreview=null;
                const messagePointerActionsEnabled=()=>!window.matchMedia('(max-width: 767.98px)').matches;
                const submitRowAction=(row,action,extraFields={})=>{
                    const selection=row?.querySelector('.message-checkbox')?.value||'';
                    if(!selection)return;
                    const form=document.createElement('form');form.method='post';form.action='/bulk-action.php';
                    const fields={_csrf:csrfToken,bulk_action:action,return_to:location.pathname+location.search,'messages[]':selection,...extraFields};
                    Object.entries(fields).forEach(([name,value])=>{const input=document.createElement('input');input.type='hidden';input.name=name;input.value=String(value);form.appendChild(input);});
                    document.body.appendChild(form);form.submit();
                };
                const bindMessageRow = row => {
                    if(row.dataset.messageBound==='1')return;
                    row.dataset.messageBound='1';
                    row.draggable=false;
                    const dragHandle=row.querySelector('.message-drag-handle');
                    if(dragHandle){
                        dragHandle.draggable=messagePointerActionsEnabled();
                        dragHandle.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();});
                    }
                    row.querySelector('[data-open-message]')?.addEventListener('click', event => { event.stopPropagation(); openMessage(row); });
                    row.addEventListener('click', event => {
                        if(Number(row.dataset.dragEndedAt||0)>Date.now()-300)return;
                        if (event.target.closest('input,button,a,select,time')) return;
                        openMessage(row);
                    });
                    row.addEventListener('contextmenu',event=>{if(!messagePointerActionsEnabled())return;event.preventDefault();openMessageContextMenu(event,row);});
                    row.addEventListener('dragstart',event=>{
                        if(!messagePointerActionsEnabled()){event.preventDefault();return;}
                        if(!event.target.closest('.message-drag-handle')){event.preventDefault();return;}
                        const selection=row.querySelector('.message-checkbox')?.value||'';if(!selection){event.preventDefault();return;}
                        draggedMessageRow=row;row.classList.add('is-dragging');
                        draggedMessagePreview?.remove();
                        const rowRect=row.getBoundingClientRect();
                        draggedMessagePreview=row.cloneNode(true);
                        draggedMessagePreview.classList.remove('is-dragging');
                        Object.assign(draggedMessagePreview.style,{position:'fixed',left:'-10000px',top:'-10000px',width:Math.min(rowRect.width,760)+'px',minHeight:rowRect.height+'px',background:getComputedStyle(row).backgroundColor,boxShadow:'0 12px 30px rgba(0,0,0,.28)',opacity:'.96',pointerEvents:'none',zIndex:'-1'});
                        document.body.appendChild(draggedMessagePreview);
                        event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('application/x-sodium-message',selection);event.dataTransfer.setData('text/plain','Déplacer ce message');
                        event.dataTransfer.setDragImage(draggedMessagePreview,24,Math.min(24,rowRect.height/2));
                    });
                    row.addEventListener('dragend',()=>{row.classList.remove('is-dragging');row.dataset.dragEndedAt=String(Date.now());draggedMessageRow=null;draggedMessagePreview?.remove();draggedMessagePreview=null;document.querySelectorAll('.folder-drop-target').forEach(target=>target.classList.remove('folder-drop-target'));});
                    row.querySelector('[data-read-toggle]')?.addEventListener('click', async event => {
                        event.stopPropagation();
                        const dot = event.currentTarget;
                        const markSeen = dot.classList.contains('is-unread');
                        const data = new FormData();
                        data.set('account_id', row.dataset.account); data.set('folder', row.dataset.folder); data.set('uid', row.dataset.uid); data.set('seen', markSeen ? '1' : '0');
                        const response = await fetch('/api/message-state.php', {method:'POST', body:data});
                        if (response.ok) {
                            const result = await response.json();
                            dot.classList.toggle('is-unread', !markSeen);
                            row.classList.toggle('unread', !markSeen);
                            dot.title = markSeen ? 'Marquer comme non lu' : 'Marquer comme lu';
                            updateUnreadBadges(result.unread_status);
                        } else showClientToast('Modification de l’état impossible.', 'danger');
                    });
                    row.querySelector('[data-star-toggle]')?.addEventListener('click', async event => {
                        event.stopPropagation();
                        const button = event.currentTarget;
                        const markFlagged = !button.classList.contains('is-flagged');
                        const data = new FormData();
                        data.set('account_id', row.dataset.account); data.set('folder', row.dataset.folder); data.set('uid', row.dataset.uid); data.set('flagged', markFlagged ? '1' : '0');
                        const response = await fetch('/api/message-state.php', {method:'POST', body:data});
                        if (response.ok) {
                            button.classList.toggle('is-flagged', markFlagged);
                            button.innerHTML = `<i class="bi bi-star${markFlagged?'-fill':''}"></i>`;
                            button.title = markFlagged ? 'Retirer des messages marqués' : 'Ajouter aux messages marqués';
                        } else showClientToast('Modification de l’étoile impossible.', 'danger');
                    });
                };
                document.querySelectorAll('[data-message-row]').forEach(bindMessageRow);
                document.querySelectorAll('[data-mail-folder-drop]').forEach(target=>{
                    const acceptsDraggedMessage=()=>draggedMessageRow&&target.dataset.account===draggedMessageRow.dataset.account&&target.dataset.folder!==draggedMessageRow.dataset.folder;
                    target.addEventListener('dragenter',event=>{if(!acceptsDraggedMessage())return;event.preventDefault();target.classList.add('folder-drop-target');});
                    target.addEventListener('dragover',event=>{if(!acceptsDraggedMessage())return;event.preventDefault();event.dataTransfer.dropEffect='move';target.classList.add('folder-drop-target');});
                    target.addEventListener('dragleave',event=>{if(!target.contains(event.relatedTarget))target.classList.remove('folder-drop-target');});
                    target.addEventListener('drop',event=>{if(!acceptsDraggedMessage())return;event.preventDefault();event.stopPropagation();const row=draggedMessageRow;target.classList.remove('folder-drop-target');if(row)submitRowAction(row,'move',{target_folder:target.dataset.folder});});
                });
                document.querySelectorAll('[data-deep-search]').forEach(button=>button.addEventListener('click',async()=>{
                    const status=button.parentElement?.querySelector('[data-deep-search-status]');
                    const list=button.closest('.bulk-mail-form')?.querySelector('.message-list');
                    if(!list||button.disabled)return;
                    button.disabled=true;
                    const original=button.innerHTML;
                    button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Recherche en cours…';
                    if(status){status.classList.remove('d-none');status.textContent='Exploration du dossier suivant…';}
                    try{
                        const params=new URLSearchParams({q:button.dataset.query||'',scope:button.dataset.scope||'all',status:button.dataset.status||'all',cursor:button.dataset.cursor||'0'});
                        const response=await fetch('/api/deep-search.php?'+params.toString(),{headers:{Accept:'application/json'}});
                        const result=await response.json();
                        if(!response.ok)throw new Error(result.error||'Recherche approfondie impossible.');
                        button.dataset.cursor=String(result.next_cursor||0);
                        let added=0;
                        if(result.html){
                            const template=document.createElement('template');template.innerHTML=result.html;
                            template.content.querySelectorAll('[data-message-row]').forEach(row=>{
                                const duplicate=[...list.querySelectorAll('[data-message-row]')].some(existing=>existing.dataset.account===row.dataset.account&&existing.dataset.folder===row.dataset.folder&&existing.dataset.uid===row.dataset.uid);
                                if(duplicate)return;
                                bindMessageRow(row);row.querySelectorAll('.human-date').forEach(bindHumanDate);list.querySelector('[data-search-empty]')?.remove();list.appendChild(row);added++;
                            });
                            refreshHumanDates();
                        }
                        if(status)status.textContent=added?`${added} résultat${added>1?'s':''} ajouté${added>1?'s':''} depuis ${result.folder}.`:`Aucun autre résultat dans ${result.folder} (${result.progress}/${result.total}).`;
                        if(result.done){button.classList.add('d-none');if(status)status.textContent='Tous les dossiers ont été parcourus.';}
                        else button.innerHTML='<i class="bi bi-chevron-down"></i> '+(added?'Plus de résultats':'Continuer la recherche');
                    }catch(error){button.innerHTML=original;showClientToast(error.message||'Recherche approfondie impossible.','danger');if(status)status.classList.add('d-none');}
                    finally{if(!button.classList.contains('d-none'))button.disabled=false;}
                }));
                readerElement?.addEventListener('hidden.bs.modal', () => {
                    closeAttachmentPreview();
                    const replies=document.getElementById('readerReplies');
                    if(replies){replies.innerHTML='';replies.classList.add('d-none');}
                    const body=document.getElementById('readerBody');
                    if(body)body.srcdoc='';
                });
                const submitReaderAction = (action, extraFields = {}) => {
                    if (!readerMessage) return;
                    const form = document.createElement('form');
                    form.method = 'post'; form.action = '/bulk-action.php';
                    const fields = {_csrf:csrfToken, bulk_action:action, return_to:location.pathname+location.search, 'messages[]':readerMessage.selection, ...extraFields};
                    Object.entries(fields).forEach(([name,value]) => { const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.appendChild(input); });
                    document.body.appendChild(form); form.submit();
                };
                document.querySelectorAll('[data-reader-action]').forEach(button => button.addEventListener('click', () => submitReaderAction(button.dataset.readerAction)));
                document.querySelector('[data-reader-select-action="move"]')?.addEventListener('click',()=>{const value=document.getElementById('readerMoveFolder')?.value;if(!value){showClientToast('Choisissez un dossier de destination.','warning');return;}submitReaderAction('move',{target_folder:value});});
                document.querySelector('[data-reader-select-action="tag"]')?.addEventListener('click',()=>{const value=document.getElementById('readerAddTag')?.value;if(!value){showClientToast('Choisissez un tag.','warning');return;}submitReaderAction('tag',{tag_id:value});});
                const showReaderImages = () => {
                    if(readerMessage?.html){
                        readerMessage.html=readerMessage.html.replace(/\sdata-sodium-src=(["'])(.*?)\1/gi,' src=$1$2$1');
                        readerMessage.renderHtml?.(readerMessage.html);
                    }
                    document.getElementById('readerSecurity')?.classList.add('d-none');
                };
                document.getElementById('readerShowImages')?.addEventListener('click', showReaderImages);
                document.getElementById('readerAlwaysImages')?.addEventListener('click', async () => {
                    if (!readerMessage?.sender_email) return;
                    const data = new FormData();
                    data.set('sender_email', readerMessage.sender_email);
                    const response = await fetch('/api/remote-images.php', {method:'POST', body:data});
                    if (!response.ok) {
                        showClientToast('Impossible d’enregistrer cette préférence.', 'danger');
                        return;
                    }
                    showReaderImages();
                    showClientToast(`Les images de ${readerMessage.sender_email} seront désormais affichées.`, 'success');
                });
                const composeEscape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
                const composeFromReader = mode => {
                    if (!readerMessage) return;
                    const replyMessage = {
                        ...readerMessage,
                        quotedHtml: readerMessage.html || document.getElementById('readerBody')?.innerHTML || ''
                    };
                    const prepareReply = () => {
                        const composeElement = document.getElementById('composeModal');
                        const composeForm = composeElement.querySelector('form');
                        composeAccount.value = String(replyMessage.account_id);
                        composeAccount.dispatchEvent(new Event('change'));
                        const toField = document.querySelector('[data-recipient-field][data-required="1"]');
                        const ccField = document.querySelector('[data-recipient-field]:not([data-required])');
                        if (mode !== 'forward' && replyMessage.reply_email) {
                            const replyName = replyMessage.reply_to_addresses?.[0]?.name || replyMessage.from;
                            toField?.dispatchEvent(new CustomEvent('recipient:add', {detail:{email:replyMessage.reply_email,name:replyName}}));
                        }
                        if (mode === 'replyAll') {
                            const excluded = new Set([replyMessage.reply_email, replyMessage.account_email].filter(Boolean).map(email => email.toLowerCase()));
                            const added = new Set();
                            [...(replyMessage.to_addresses || []), ...(replyMessage.cc_addresses || [])].forEach(address => {
                                const email = String(address.email || '').toLowerCase();
                                if (!email || excluded.has(email) || added.has(email)) return;
                                added.add(email);
                                ccField?.dispatchEvent(new CustomEvent('recipient:add', {detail:{email,name:address.name || email}}));
                            });
                        }
                        composeForm.querySelector('[name="subject"]').value = mode === 'forward'
                            ? (/^(tr|fwd):/i.test(replyMessage.subject) ? replyMessage.subject : 'Tr: ' + replyMessage.subject)
                            : (/^re:/i.test(replyMessage.subject) ? replyMessage.subject : 'Re: ' + replyMessage.subject);
                        composeForm.querySelector('.modal-title').innerHTML = mode === 'forward' ? '<i class="bi bi-forward me-2"></i>Transférer un message' : '<i class="bi bi-reply me-2"></i>Répondre';
                        if (mode !== 'forward') {
                            composeForm.querySelector('[name="reply_account_id"]').value = replyMessage.account_id;
                            composeForm.querySelector('[name="reply_message_key"]').value = replyMessage.message_key;
                            composeForm.querySelector('[name="reply_message_id"]').value = replyMessage.message_id || '';
                        } else {
                            composeForm.querySelector('[name="forward_account_id"]').value = replyMessage.account_id;
                            composeForm.querySelector('[name="forward_folder"]').value = replyMessage.folder || 'INBOX';
                            composeForm.querySelector('[name="forward_uid"]').value = replyMessage.uid;
                            const forwardedList = composeForm.querySelector('.forwarded-attachment-list');
                            (replyMessage.attachments || []).filter(file => !file.inline).forEach(file => {
                                const input = document.createElement('input');
                                input.type = 'hidden'; input.name = 'forward_parts[]'; input.value = file.part; input.dataset.forwardPart = '1';
                                const item = document.createElement('div'); item.className = 'attachment-file-item';
                                const label = document.createElement('span'); label.textContent = file.name || 'Pièce jointe';
                                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-link text-danger'; remove.innerHTML = '<i class="bi bi-x-lg"></i>';
                                remove.addEventListener('click', () => { input.remove(); item.remove(); });
                                item.append(label, remove); forwardedList?.appendChild(item); composeForm.appendChild(input);
                            });
                        }
                        let replyPrepared = false;
                        const finalizeReply = () => {
                            if (replyPrepared) return;
                            replyPrepared = true;
                            const editor = composeForm.querySelector('.rich-editor-content');
                            editor.replaceChildren();
                            const replySpace = document.createElement('p');
                            replySpace.innerHTML = '<br>';
                            editor.appendChild(replySpace);
                            if (mode === 'forward') {
                                const quote = document.createElement('div');
                                quote.dataset.sodiumQuote = '1';
                                quote.innerHTML = `<p><strong>---------- Message transféré ----------</strong><br><strong>De :</strong> ${composeEscape(replyMessage.from)}<br><strong>Date :</strong> ${composeEscape(replyMessage.date)}<br><strong>Objet :</strong> ${composeEscape(replyMessage.subject || '(pas d’objet)')}<br><strong>À :</strong> ${composeEscape(replyMessage.to)}${replyMessage.cc ? `<br><strong>Cc :</strong> ${composeEscape(replyMessage.cc)}` : ''}</p><blockquote>${replyMessage.quotedHtml}</blockquote>`;
                                editor.appendChild(quote);
                            } else if (quoteReplyEnabled) {
                                const quote = document.createElement('div');
                                quote.dataset.sodiumQuote = '1';
                                const heading = document.createElement('p');
                                heading.className = 'sodium-quote-heading';
                                heading.textContent = `Le ${replyMessage.date || ''}, ${replyMessage.from || 'l’expéditeur'} a écrit :`;
                                const blockquote = document.createElement('blockquote');
                                blockquote.innerHTML = replyMessage.quotedHtml;
                                quote.append(heading, blockquote);
                                editor.appendChild(quote);
                            }
                            applyComposeSignature();
                            focusComposeEditor();
                        };
                        composeElement.addEventListener('shown.bs.modal', finalizeReply, {once:true});
                        bootstrap.Modal.getOrCreateInstance(composeElement).show();
                        window.setTimeout(finalizeReply, 400);
                    };
                    readerElement.addEventListener('hidden.bs.modal', prepareReply, {once:true});
                    readerModal.hide();
                };
                document.getElementById('readerReply')?.addEventListener('click', () => composeFromReader('reply'));
                document.getElementById('readerReplyAll')?.addEventListener('click', () => composeFromReader('replyAll'));
                document.getElementById('readerForward')?.addEventListener('click', () => composeFromReader('forward'));

                let messageContextMenu=null;
                const closeMessageContextMenu=()=>{messageContextMenu?.remove();messageContextMenu=null;};
                const contextMenuButton=(icon,label,handler,{disabled=false,tone='',submenu=null}={})=>{
                    const item=document.createElement('div');item.className='mail-context-item';
                    const button=document.createElement('button');button.type='button';button.className=`mail-context-action${tone?` text-${tone}`:''}`;button.disabled=disabled;
                    button.innerHTML=`<i class="bi ${icon}"></i><span>${label}</span>${submenu?'<i class="bi bi-chevron-right ms-auto"></i>':''}`;
                    if(handler&&!disabled)button.addEventListener('click',event=>{event.stopPropagation();closeMessageContextMenu();handler();});
                    item.appendChild(button);if(submenu){item.classList.add('has-submenu');item.appendChild(submenu);}return item;
                };
                const contextSubmenu=items=>{const submenu=document.createElement('div');submenu.className='mail-context-submenu';items.forEach(item=>submenu.appendChild(item));return submenu;};
                const openMessageContextMenu=(event,row)=>{
                    closeMessageContextMenu();
                    const menu=document.createElement('div');menu.className='mail-context-menu';menu.setAttribute('role','menu');
                    const compose=async mode=>{await openMessage(row);composeFromReader(mode);};
                    menu.append(
                        contextMenuButton('bi-reply','Répondre',()=>compose('reply')),
                        contextMenuButton('bi-reply-all','Répondre à tous',()=>compose('replyAll'),{disabled:row.dataset.replyAll!=='1'}),
                        contextMenuButton('bi-forward','Transférer',()=>compose('forward'))
                    );
                    const separator=document.createElement('div');separator.className='mail-context-separator';menu.appendChild(separator);
                    menu.append(
                        contextMenuButton('bi-archive','Archiver',()=>submitRowAction(row,'archive')),
                        contextMenuButton('bi-trash','Supprimer',()=>submitRowAction(row,'trash'),{tone:'danger'}),
                        contextMenuButton('bi-exclamation-octagon','Indésirable',()=>submitRowAction(row,'junk'),{tone:'warning'}),
                        contextMenuButton(row.classList.contains('unread')?'bi-envelope-open':'bi-envelope','Marquer comme '+(row.classList.contains('unread')?'lu':'non lu'),()=>row.querySelector('[data-read-toggle]')?.click())
                    );
                    const folders=(readerFolders[String(row.dataset.account)]||[]).filter(folder=>folder.key!==row.dataset.folder);
                    const folderItems=folders.map(folder=>contextMenuButton('bi-folder',folder.label,()=>submitRowAction(row,'move',{target_folder:folder.key})));
                    menu.appendChild(contextMenuButton('bi-folder-symlink','Déplacer vers',null,{disabled:folderItems.length===0,submenu:folderItems.length?contextSubmenu(folderItems):null}));
                    const presentTagIds=new Set([...row.querySelectorAll('[data-tag-id]')].map(tag=>String(tag.dataset.tagId)));
                    const tags=(readerTags[String(row.dataset.account)]||[]).filter(tag=>!presentTagIds.has(String(tag.id)));
                    const tagItems=tags.map(tag=>contextMenuButton('bi-tag',tag.name,()=>submitRowAction(row,'tag',{tag_id:tag.id})));
                    menu.appendChild(contextMenuButton('bi-tags','Ajouter un tag',null,{disabled:tagItems.length===0,submenu:tagItems.length?contextSubmenu(tagItems):null}));
                    document.body.appendChild(menu);messageContextMenu=menu;
                    const margin=8;let left=Math.min(event.clientX,window.innerWidth-menu.offsetWidth-margin);let top=Math.min(event.clientY,window.innerHeight-menu.offsetHeight-margin);
                    menu.style.left=Math.max(margin,left)+'px';menu.style.top=Math.max(margin,top)+'px';
                    if(menu.getBoundingClientRect().right+280>window.innerWidth)menu.classList.add('submenus-left');
                };
                document.addEventListener('click',event=>{if(messageContextMenu&&!messageContextMenu.contains(event.target))closeMessageContextMenu();});
                document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMessageContextMenu();});
                window.addEventListener('blur',closeMessageContextMenu);
                window.addEventListener('resize',closeMessageContextMenu);
                window.addEventListener('scroll',closeMessageContextMenu,true);

                const composeElement = document.getElementById('composeModal');
                const composeLeaveElement = document.getElementById('composeLeaveModal');
                const composeLeaveModal = composeLeaveElement ? bootstrap.Modal.getOrCreateInstance(composeLeaveElement) : null;
                let allowComposeClose = false;
                const composeHasDraftableContent = () => {
                    const form = composeElement?.querySelector('form');
                    if (!form) return false;
                    if (form.querySelector('[name="to_email"]')?.value || form.querySelector('[name="cc_email"]')?.value || form.querySelector('[name="bcc_email"]')?.value || form.querySelector('[name="subject"]')?.value.trim()) return true;
                    if (form.querySelector('input[type="file"]')?.files?.length || form.querySelector('[data-forward-part]')) return true;
                    const editor = form.querySelector('.rich-editor-content');
                    if (!editor) return false;
                    const copy = editor.cloneNode(true);
                    copy.querySelectorAll('[data-sodium-signature], [data-sodium-signature-spacer]').forEach(element => element.remove());
                    return Boolean(copy.textContent.trim() || copy.querySelector('img,table,blockquote,[data-sodium-quote],[data-sodium-template]'));
                };
                composeElement?.addEventListener('hide.bs.modal', event => {
                    if (composeSubmissionInProgress || allowComposeClose || !composeHasDraftableContent()) return;
                    event.preventDefault();
                    composeLeaveModal?.show();
                });
                composeLeaveElement?.querySelector('[data-compose-leave="continue"]')?.addEventListener('click', () => composeLeaveModal?.hide());
                composeLeaveElement?.addEventListener('hidden.bs.modal', () => {
                    if (composeElement?.classList.contains('show')) document.body.classList.add('modal-open');
                });
                composeLeaveElement?.querySelector('[data-compose-leave="discard"]')?.addEventListener('click', () => {
                    allowComposeClose = true;
                    composeLeaveModal?.hide();
                    bootstrap.Modal.getOrCreateInstance(composeElement).hide();
                });
                composeLeaveElement?.querySelector('[data-compose-leave="draft"]')?.addEventListener('click', () => {
                    const form = composeElement?.querySelector('form');
                    const draftButton = form?.querySelector('[name="compose_action"][value="draft"]');
                    if (!form || !draftButton) return;
                    composeLeaveModal?.hide();
                    form.noValidate = true;
                    form.requestSubmit(draftButton);
                    window.setTimeout(() => { form.noValidate = false; }, 0);
                });
                composeElement?.addEventListener('hidden.bs.modal', () => {
                    allowComposeClose = false;
                    cancelScheduledEdit(false);
                    const form = document.querySelector('#composeModal form');
                    form?.reset();
                    form?.querySelectorAll('[data-recipient-field]').forEach(field => field.dispatchEvent(new Event('recipient:reset')));
                    const editor = form?.querySelector('.rich-editor-content');
                    const textarea = form?.querySelector('.rich-editor textarea');
                    if (editor) editor.innerHTML = '';
                    if (textarea) textarea.value = '';
                    form?.querySelectorAll('input[type="file"]').forEach(input => { input.value = ''; });
                    form?.querySelectorAll('input[type="file"]').forEach(input => input.dispatchEvent(new Event('attachments:reset')));
                    form?.querySelectorAll('.attachment-file-list').forEach(list => { list.innerHTML = ''; });
                    form?.querySelectorAll('.forwarded-attachment-list').forEach(list => { list.innerHTML = ''; });
                    form?.querySelectorAll('[data-forward-part]').forEach(input => input.remove());
                    form?.querySelectorAll('[name="reply_account_id"],[name="reply_message_key"],[name="reply_message_id"],[name="forward_account_id"],[name="forward_folder"],[name="forward_uid"]').forEach(input => { input.value = ''; });
                    const modalTitle = form?.querySelector('.modal-title');
                    if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Nouveau message';
                    syncSignatures();
                });

                const autoDraftForm=document.querySelector('#composeModal form');let autoDraftDirty=false;let autoDraftSaving=false;
                autoDraftForm?.addEventListener('input',()=>{if(!composeSubmissionInProgress)autoDraftDirty=true;});
                autoDraftForm?.addEventListener('change',()=>{if(!composeSubmissionInProgress)autoDraftDirty=true;});
                const saveAutoDraft=async()=>{if(!autoDraftForm||!autoDraftDirty||autoDraftSaving||composeSubmissionInProgress||scheduledEditId||!document.getElementById('composeModal')?.classList.contains('show'))return;const editor=autoDraftForm.querySelector('.rich-editor-content');const textarea=autoDraftForm.querySelector('textarea[name="content"]');if(textarea)textarea.value=editor?.innerHTML||'';if(!(textarea?.value||autoDraftForm.querySelector('[name="subject"]')?.value||autoDraftForm.querySelector('[name="to_email"]')?.value))return;autoDraftSaving=true;try{const response=await fetch('/api/autodraft.php',{method:'POST',body:new FormData(autoDraftForm),headers:{Accept:'application/json'}});const result=await response.json();if(response.ok&&result.ok){const id=autoDraftForm.querySelector('[name="compose_id"]');if(id)id.value=String(result.compose_id);autoDraftDirty=false;}}catch(error){}finally{autoDraftSaving=false;}};
                window.setInterval(saveAutoDraft,30000);
                document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')saveAutoDraft();});

                document.querySelectorAll('.compose-attachments').forEach(panel => {
                    const input = panel.querySelector('.attachment-dropzone input[type="file"]');
                    const list = panel.querySelector('.attachment-file-list');
                    if (!input || !list) return;
                    let dragDepth = 0;
                    let uploadedFiles = [];
                    let uploadsRunning = 0;
                    const renderFiles = () => {
                        list.innerHTML = '';
                        uploadedFiles.forEach((file, index) => {
                            const item = document.createElement('div'); item.className='attachment-file-item';
                            const size = file.size < 1024*1024 ? `${Math.ceil(file.size/1024)} Ko` : `${(file.size/1024/1024).toFixed(1)} Mo`;
                            const copy = document.createElement('div');copy.className='attachment-upload-copy';
                            const label=document.createElement('span');label.textContent=`${file.name} · ${size}`;copy.appendChild(label);
                            if(file.state==='uploading'){const progress=document.createElement('div');progress.className='progress';progress.setAttribute('role','progressbar');progress.innerHTML=`<div class="progress-bar progress-bar-striped progress-bar-animated" style="width:${file.progress||0}%"></div>`;copy.appendChild(progress);}
                            if(file.state==='error'){const error=document.createElement('small');error.className='text-danger';error.textContent=file.error||'Échec du chargement';copy.appendChild(error);}
                            const remove = document.createElement('button');
                            remove.type = 'button';
                            remove.className = 'btn btn-sm btn-link text-danger';
                            remove.title = 'Retirer cette pièce jointe';
                            remove.setAttribute('aria-label', `Retirer ${file.name}`);
                            remove.innerHTML = '<i class="bi bi-x-lg"></i>';
                            remove.addEventListener('click', () => {
                                const removed=uploadedFiles.splice(index,1)[0];
                                if(removed?.token){const data=new FormData();data.set('action','delete');data.set('token',removed.token);fetch('/api/temp-attachment.php',{method:'POST',body:data,headers:{Accept:'application/json'}}).catch(()=>{});}
                                renderFiles();
                            });
                            item.append(copy, remove);
                            list.appendChild(item);
                        });
                    };
                    const uploadFile = file => new Promise(resolve=>{
                        const entry={name:file.name,size:file.size,state:'uploading',progress:0,token:''};uploadedFiles.push(entry);uploadsRunning++;renderFiles();
                        const xhr=new XMLHttpRequest();xhr.open('POST','/api/temp-attachment.php');xhr.responseType='json';xhr.setRequestHeader('Accept','application/json');xhr.setRequestHeader('X-CSRF-Token',csrfToken);
                        xhr.upload.addEventListener('progress',event=>{if(event.lengthComputable){entry.progress=Math.round(event.loaded/event.total*100);renderFiles();}});
                        xhr.addEventListener('load',()=>{if(xhr.status===401||xhr.status===419){window.location.assign('/login.php?expired=1');return;}const result=xhr.response||{};if(xhr.status>=200&&xhr.status<300&&result.ok){entry.state='done';entry.progress=100;entry.token=result.token;entry.name=result.name||entry.name;}else{entry.state='error';entry.error=result.error||'Échec du chargement';}uploadsRunning--;renderFiles();resolve();});
                        xhr.addEventListener('error',()=>{entry.state='error';entry.error='Connexion interrompue';uploadsRunning--;renderFiles();resolve();});
                        const data=new FormData();data.set('attachment',file,file.name);xhr.send(data);
                    });
                    const addFiles = async files => {
                        const additions = Array.from(files);
                        const totalSize = uploadedFiles.reduce((total, file) => total + file.size, 0)+additions.reduce((total,file)=>total+file.size,0);
                        if (totalSize > 25 * 1024 * 1024) {
                            showClientToast('Les pièces jointes dépassent 25 Mo au total.', 'danger');
                            return;
                        }
                        input.value='';
                        for(const file of additions)await uploadFile(file);
                    };
                    input.addEventListener('change', () => {
                        const additions = Array.from(input.files);
                        addFiles(additions);
                    });
                    input.addEventListener('attachments:reset', () => {
                        uploadedFiles.forEach(file=>{if(file.token){const data=new FormData();data.set('action','delete');data.set('token',file.token);fetch('/api/temp-attachment.php',{method:'POST',body:data,headers:{Accept:'application/json'}}).catch(()=>{});}});
                        uploadedFiles = [];
                        renderFiles();
                    });
                    panel.closest('form')?.addEventListener('submit',event=>{
                        if(uploadsRunning||uploadedFiles.some(file=>file.state==='uploading')){event.preventDefault();composeSubmissionInProgress=false;showClientToast('Patientez jusqu’à la fin du chargement des pièces jointes.','warning');return;}
                        panel.closest('form').querySelectorAll('input[data-temp-attachment]').forEach(hidden=>hidden.remove());
                        uploadedFiles.filter(file=>file.state==='done'&&file.token).forEach(file=>{const hidden=document.createElement('input');hidden.type='hidden';hidden.name='attachment_tokens[]';hidden.value=file.token;hidden.dataset.tempAttachment='1';panel.closest('form').appendChild(hidden);});
                    });
                    panel.addEventListener('dragenter', event => {
                        event.preventDefault();
                        dragDepth++;
                        panel.classList.add('is-dragover');
                    });
                    panel.addEventListener('dragover', event => {
                        event.preventDefault();
                        if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
                    });
                    panel.addEventListener('dragleave', event => {
                        event.preventDefault();
                        dragDepth = Math.max(0, dragDepth - 1);
                        if (!dragDepth) panel.classList.remove('is-dragover');
                    });
                    panel.addEventListener('drop', event => {
                        event.preventDefault();
                        dragDepth = 0;
                        panel.classList.remove('is-dragover');
                        if (event.dataTransfer?.files?.length) addFiles(event.dataTransfer.files);
                    });
                });

                const notificationToggle = document.getElementById('notificationToggle');
                const updatePushSubscription = async enabled => {
                    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
                    const registration = await navigator.serviceWorker.ready;
                    let subscription = await registration.pushManager.getSubscription();
                    if (enabled && !subscription) {
                        const keyResponse = await fetch('/api/push-subscription.php', {headers:{Accept:'application/json'}});
                        if (!keyResponse.ok) throw new Error('Clé Push indisponible');
                        const {public_key:publicKey} = await keyResponse.json();
                        const padding='='.repeat((4-publicKey.length%4)%4);
                        const bytes=Uint8Array.from(atob((publicKey+padding).replace(/-/g,'+').replace(/_/g,'/')),character=>character.charCodeAt(0));
                        subscription = await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:bytes});
                    }
                    if (!subscription) return;
                    await fetch('/api/push-subscription.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/json','Accept':'application/json'},
                        body:JSON.stringify({endpoint:subscription.endpoint,enabled})
                    });
                    if (!enabled) await subscription.unsubscribe();
                };
                const renderNotificationState = () => {
                    if (!notificationToggle) return;
                    const supported = 'Notification' in window;
                    const active = supported && Notification.permission === 'granted' && localStorage.getItem('sodiumNotifications') === '1';
                    const blocked = supported && Notification.permission === 'denied';
                    const label = active ? 'Notifications activées — cliquer pour mettre en silencieux' : (blocked ? 'Notifications bloquées par le navigateur' : 'Notifications désactivées — cliquer pour activer');
                    notificationToggle.classList.toggle('text-danger', active);
                    notificationToggle.setAttribute('title', label);
                    notificationToggle.setAttribute('aria-label', label);
                    notificationToggle.setAttribute('aria-pressed', active ? 'true' : 'false');
                    const icon = notificationToggle.querySelector('i');
                    if (icon) icon.className = `bi ${active ? 'bi-bell-fill' : 'bi-bell-slash'}`;
                };
                renderNotificationState();
                notificationToggle?.addEventListener('click', async () => {
                    if (!('Notification' in window)) return;
                    if (Notification.permission === 'granted' && localStorage.getItem('sodiumNotifications') === '1') {
                        localStorage.setItem('sodiumNotifications', '0');
                        showClientToast('Notifications mises en silencieux.', 'info');
                    } else {
                        const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
                        if (permission === 'granted') {
                            localStorage.setItem('sodiumNotifications', '1');
                            try { await updatePushSubscription(true); } catch (error) { showClientToast('Notifications actives dans Sodium, mais le Push en arrière-plan est indisponible.', 'warning'); }
                            showClientToast('Notifications activées.', 'success');
                        } else {
                            showClientToast('Les notifications sont bloquées dans le navigateur.', 'warning');
                        }
                    }
                    if (localStorage.getItem('sodiumNotifications') !== '1') {
                        try { await updatePushSubscription(false); } catch (error) {}
                    }
                    renderNotificationState();
                });
                let mailStatusRunning = false;
                const checkMailStatus = async () => {
                    if (!hasMailAccounts) return;
                    if (mailStatusRunning) return;
                    mailStatusRunning = true;
                    try {
                        const refreshData = new FormData();
                        refreshData.set('refresh', '1');
                        const response = await fetch('/api/status.php', {method:'POST', body:refreshData, headers: {'Accept':'application/json'}});
                        if (!response.ok) { showClientToast('La relève du courrier a échoué.', 'danger'); return; }
                        const status = await response.json();
                        updateUnreadBadges(status);
                        const stored = localStorage.getItem('sodiumUnifiedUnread');
                        const previous = Number(stored === null ? status.unified_unread : stored);
                        if (status.unified_unread > previous && localStorage.getItem('sodiumNotifications') === '1' && 'Notification' in window && Notification.permission === 'granted') {
                            const delta = status.unified_unread - previous;
                            new Notification('Sodium — Nouveau message', {body: delta > 1 ? `${delta} nouveaux messages reçus` : 'Un nouveau message a été reçu', icon: '/assets/icons/pwa-192.png'});
                        }
                        localStorage.setItem('sodiumUnifiedUnread', String(status.unified_unread));
                        if(Number(status.new_messages||0)>0)showClientToast(`${status.new_messages} nouveau${Number(status.new_messages)>1?'x':''} message${Number(status.new_messages)>1?'s':''}.`,'info');
                        const canReload = stored !== null && previous !== status.unified_unread && document.querySelector('[data-message-row]') && !document.querySelector('.modal.show') && !document.querySelector('.message-checkbox:checked') && !document.querySelector('.rich-editor-content:focus');
                        if (canReload) window.location.reload();
                    } catch (error) {
                        showClientToast('La relève du courrier est momentanément indisponible.', 'danger');
                    } finally {
                        mailStatusRunning = false;
                    }
                };
                if (hasMailAccounts) window.setInterval(checkMailStatus, <?= max(1,(int)$sodiumSettings['refresh_interval']) ?> * 60000);
                const checkCachedMailStatus = async () => {
                    if (!hasMailAccounts) return;
                    if (mailStatusRunning) return;
                    try {
                        const response=await fetch('/api/status.php',{headers:{Accept:'application/json'}});
                        if(!response.ok)return;
                        const status=await response.json();
                        const previous=Number(localStorage.getItem('sodiumUnifiedUnread')??status.unified_unread);
                        updateUnreadBadges(status);
                        localStorage.setItem('sodiumUnifiedUnread',String(status.unified_unread));
                        if(status.unified_unread>previous&&localStorage.getItem('sodiumNotifications')==='1'&&'Notification' in window&&Notification.permission==='granted'){
                            new Notification('Sodium — Nouveau message',{body:'Du nouveau courrier est disponible.',icon:'/assets/icons/pwa-192.png'});
                        }
                        const canReload=previous!==Number(status.unified_unread)&&document.querySelector('[data-message-row]')&&!document.querySelector('.modal.show')&&!document.querySelector('.message-checkbox:checked')&&!document.querySelector('.rich-editor-content:focus');
                        if(canReload)window.location.reload();
                    }catch(error){}
                };
                const syncVisibleMessageStates = async () => {
                    const rows=[...document.querySelectorAll('[data-message-row]')];
                    if(!rows.length)return;
                    try{
                        const response=await fetch('/api/visible-message-states.php',{
                            method:'POST',
                            headers:{'Content-Type':'application/json','Accept':'application/json'},
                            body:JSON.stringify({messages:rows.map(row=>({account_id:Number(row.dataset.account),folder:row.dataset.folder,uid:Number(row.dataset.uid)}))})
                        });
                        if(!response.ok)return;
                        const payload=await response.json();
                        const stateMap=new Map((payload.states||[]).map(state=>[`${state.account_id}|${state.folder}|${state.uid}`,state]));
                        rows.forEach(row=>{
                            const state=stateMap.get(`${row.dataset.account}|${row.dataset.folder}|${row.dataset.uid}`);
                            if(!state)return;
                            row.classList.toggle('unread',Boolean(state.unread));
                            const dot=row.querySelector('[data-read-toggle]');
                            if(dot){
                                dot.classList.toggle('is-unread',Boolean(state.unread));
                                dot.title=state.unread?'Marquer comme lu':'Marquer comme non lu';
                            }
                            const star=row.querySelector('[data-star-toggle]');
                            if(star){
                                star.classList.toggle('is-flagged',Boolean(state.flagged));
                                star.innerHTML=`<i class="bi bi-star${state.flagged?'-fill':''}"></i>`;
                                star.title=state.flagged?'Retirer des messages marqués':'Ajouter aux messages marqués';
                            }
                        });
                    }catch(error){}
                };
                if (hasMailAccounts) {
                    checkCachedMailStatus();
                    syncVisibleMessageStates();
                    window.setInterval(checkCachedMailStatus,10000);
                    window.setInterval(syncVisibleMessageStates,10000);
                }
                if (hasMailAccounts) window.addEventListener('focus', () => {
                    const lastCheck = Number(sessionStorage.getItem('sodiumLastFocusCheck') || 0);
                    if (Date.now() - lastCheck < 15000) return;
                    sessionStorage.setItem('sodiumLastFocusCheck', String(Date.now()));
                    checkCachedMailStatus();
                    syncVisibleMessageStates();
                });

                const resetMailRefreshButtons = () => {
                    document.querySelectorAll('[data-mail-refresh]').forEach(form => {
                        form.dataset.submitting = '';
                        const button = form.querySelector('button[type="submit"], button:not([type])');
                        const icon = button?.querySelector('.bi-arrow-repeat');
                        if (button) {
                            button.disabled = false;
                            button.removeAttribute('aria-busy');
                        }
                        icon?.classList.remove('mail-refresh-spinning');
                    });
                };
                document.querySelectorAll('[data-mail-refresh]').forEach(form => {
                    form.addEventListener('submit', event => {
                        if (form.dataset.submitting === '1') {
                            event.preventDefault();
                            return;
                        }
                        form.dataset.submitting = '1';
                        const button = form.querySelector('button[type="submit"], button:not([type])');
                        const icon = button?.querySelector('.bi-arrow-repeat');
                        if (button) {
                            button.disabled = true;
                            button.setAttribute('aria-busy', 'true');
                        }
                        icon?.classList.add('mail-refresh-spinning');
                    });
                });
                window.addEventListener('pageshow', resetMailRefreshButtons);
            })();
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {}));
            }
        </script>
    </body>
    </html>
    <?php
}
