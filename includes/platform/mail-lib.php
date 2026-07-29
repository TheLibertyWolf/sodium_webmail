<?php
declare(strict_types=1);

function mail_table_exists(string $table): bool { return table_exists_local($table); }

function mail_ensure_schema(): void
{
    static $ready = false;
    if ($ready) return;
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        provider VARCHAR(20) NOT NULL DEFAULT 'smtp',
        from_email VARCHAR(190) NOT NULL,
        from_name VARCHAR(190) NULL,
        reply_to VARCHAR(190) NULL,
        smtp_host VARCHAR(190) NULL,
        smtp_port INT NULL,
        smtp_username VARCHAR(190) NULL,
        smtp_password TEXT NULL,
        smtp_encryption VARCHAR(20) NULL,
        brevo_api_key TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NULL,
        header_html MEDIUMTEXT NULL,
        content_html MEDIUMTEXT NULL,
        footer_html MEDIUMTEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NULL,
        template_id INT NULL,
        related_type VARCHAR(60) NULL,
        related_id INT NULL,
        to_email VARCHAR(190) NOT NULL,
        to_name VARCHAR(190) NULL,
        subject VARCHAR(255) NOT NULL,
        content_html MEDIUMTEXT NULL,
        final_html MEDIUMTEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'queued',
        scheduled_at DATETIME NULL,
        sent_at DATETIME NULL,
        error_message TEXT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_mail_queue_status (status, scheduled_at),
        INDEX idx_mail_queue_related (related_type, related_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_by INT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_pdf_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        token CHAR(64) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        INDEX idx_document_pdf_tokens_document (document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS legal_document_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token CHAR(64) NOT NULL UNIQUE,
        file_path VARCHAR(500) NOT NULL,
        download_name VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        created_by INT NULL,
        INDEX idx_legal_document_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mail_seed_default_template();
    mail_seed_system_settings();
    $ready = true;
}

function mail_seed_system_settings(): void
{
    global $pdo;
    $accountStmt = $pdo->prepare("SELECT id FROM mail_accounts
        WHERE LOWER(from_email)=LOWER(?)
        ORDER BY is_active DESC, id LIMIT 1");
    $accountStmt->execute([function_exists('sodium_system_sender_email')?sodium_system_sender_email():'no-reply@localhost']);
    $accountId = (int)($accountStmt->fetchColumn() ?: 0);
    if ($accountId > 0) {
        $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key,setting_value) VALUES ('system_mail_account_id',?)")
            ->execute([(string)$accountId]);
    }
    $templateStmt = $pdo->query('SELECT id FROM mail_templates WHERE id=3 LIMIT 1');
    $templateId = (int)($templateStmt->fetchColumn() ?: 0);
    if ($templateId > 0) {
        $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key,setting_value) VALUES ('system_mail_template_id',?)")
            ->execute([(string)$templateId]);
    }
}

function app_setting(string $key, ?string $default = null): ?string
{
    global $pdo;
    mail_ensure_schema();
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function app_setting_save(string $key, string $value): void
{
    global $pdo;
    mail_ensure_schema();
    $pdo->prepare('INSERT INTO app_settings (setting_key,setting_value,updated_by)
        VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
        updated_by=VALUES(updated_by),updated_at=NOW()')
        ->execute([$key,$value,(int)(current_user()['id'] ?? 0) ?: null]);
}

function system_mail_account_id(): int
{
    return (int)(function_exists('sodium_instance_settings')?(sodium_instance_settings()['system_mail_account_id']??0):0);
}

function system_mail_template_id(): int
{
    global $pdo;$configured=(int)(app_setting('system_mail_template_id','0')??0);if($configured)return$configured;mail_ensure_schema();return(int)($pdo->query('SELECT id FROM mail_templates WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn()?:0);
}

function legal_public_document_url(string $filePath, string $downloadName): string
{
    global $pdo;
    mail_ensure_schema();
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO legal_document_tokens (token,file_path,download_name,expires_at,created_by) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY),?)')
        ->execute([$token, $filePath, $downloadName, (int)(current_user()['id'] ?? 0) ?: null]);
    return BASE_URL . '/legal-document.php?token=' . $token;
}

function mail_seed_default_template(): void
{
    global $pdo;
    $confidentiality = '<div style="margin-top:20px;padding-top:12px;border-top:1px solid #e2e8f0;color:#94a3b8;font-family:Arial,sans-serif;font-size:9px;line-height:1.4;text-align:justify">Les informations contenues dans ce courrier électronique et tout fichier joint sont destinées exclusivement à l’usage de la personne ou de l’entité à laquelle elles sont adressées. Si vous n’êtes pas le destinataire prévu, toute divulgation, copie, distribution ou toute action prise ou omise en vous basant sur ces informations sont strictement interdites et pourraient être illégales. Si vous avez reçu ce courrier électronique par erreur, veuillez en informer l’expéditeur immédiatement et supprimer le courrier électronique original et tout fichier attaché de votre système. Nous ne pouvons pas garantir que ce courrier électronique soit exempt de maliciel, et il est de la responsabilité du destinataire de s’assurer que ce courrier électronique et tout fichier joint ne contiennent pas de composants dangereux avant de les ouvrir ou de les utiliser. L’expéditeur décline toute responsabilité en cas de dommages causés par un quelconque virus transmis par ce courrier électronique.</div>';
    $count = (int)$pdo->query('SELECT COUNT(*) FROM mail_templates')->fetchColumn();
    if ($count > 0) {
        $stmt = $pdo->prepare("UPDATE mail_templates SET footer_html=REPLACE(COALESCE(footer_html,''), ?, '') WHERE name='Document simple'");
        $stmt->execute([$confidentiality]);
        $stmt = $pdo->prepare("UPDATE mail_templates SET footer_html=CONCAT(COALESCE(footer_html,''), ?) WHERE name='Default' AND COALESCE(footer_html,'') NOT LIKE '%destinataire prévu%'");
        $stmt->execute([$confidentiality]);
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO mail_templates (name, subject, header_html, content_html, footer_html, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $stmt->execute([
        'Document simple',
        '{{subject}}',
        '<div style="font-family:Arial,sans-serif;color:#1f2937"><h2 style="margin:0 0 16px;color:#132238">Sodium</h2>',
        '{{content}}',
        '<p style="margin-top:24px;color:#64748b;font-size:12px">Message généré depuis Sodium.</p></div>',
    ]);
}

function mails_can_access(?int $userId = null): bool { return user_has_aptitude('mails_view', $userId) || user_has_aptitude('mails_gestion', $userId); }
function mails_can_manage(?int $userId = null): bool { return user_has_aptitude('mails_gestion', $userId); }

function mail_queue_menu_counts(): array
{
    global $pdo;
    $out = ['warning' => 0, 'danger' => 0];
    if (!mail_table_exists('mail_queue')) return $out;
    $stmt = $pdo->query("SELECT status, COUNT(*) c FROM mail_queue WHERE status IN ('queued','failed') GROUP BY status");
    foreach ($stmt->fetchAll() as $row) {
        if ($row['status'] === 'queued') $out['warning'] = (int)$row['c'];
        if ($row['status'] === 'failed') $out['danger'] = (int)$row['c'];
    }
    return $out;
}

function mail_badges_html(array $counts): string
{
    $html = '<span class="badge-rail ms-auto">';
    foreach (['warning' => 'warning', 'danger' => 'danger'] as $key => $class) {
        if (!empty($counts[$key])) {
            $html .= '<span class="badge text-bg-' . $class . '">' . (int)$counts[$key] . '</span>';
        }
    }
    return $html . '</span>';
}

function mail_accounts(bool $activeOnly = false): array
{
    global $pdo;
    $sql = 'SELECT * FROM mail_accounts' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY is_active DESC, name';
    return $pdo->query($sql)->fetchAll();
}

function mail_templates(bool $activeOnly = false): array
{
    global $pdo;
    $sql = 'SELECT * FROM mail_templates' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY is_active DESC, name';
    return $pdo->query($sql)->fetchAll();
}

function mail_render_template(?array $template, string $content, string $subject = '', array $vars = []): string
{
    $html = $template
        ? (string)($template['header_html'] ?? '') . ((string)($template['content_html'] ?? '') ?: '{{content}}') . (string)($template['footer_html'] ?? '')
        : '{{content}}';
    $replacements = [
        '{{content}}' => $content,
        '{{subject}}' => $subject,
        '{{structure_name}}' => (string)($vars['structure_name'] ?? 'Sodium'),
        '{{year}}' => (string)($vars['year'] ?? date('Y')),
    ];
    foreach ($vars as $key => $value) {
        $replacements['{{' . $key . '}}'] = (string)$value;
    }
    $rendered = strtr($html, $replacements);
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'
        . 'html,body{margin:0!important;padding:0!important;width:100%!important;background:#f1f5f9}*{box-sizing:border-box}img{display:block;max-width:100%!important;height:auto!important}table{border-collapse:collapse;max-width:100%!important}a{overflow-wrap:anywhere}.sodium-mail-content{font-family:Arial,sans-serif;color:#1f2937;font-size:15px;line-height:1.55;overflow-wrap:anywhere}'
        . '@media only screen and (max-width:600px){.sodium-mail-shell{width:100%!important}.sodium-mail-content{padding:18px 14px!important;font-size:15px!important}.sodium-mail-content table{width:100%!important}.sodium-mail-content td,.sodium-mail-content th{max-width:100%!important}.sodium-mail-content h1{font-size:24px!important}.sodium-mail-content h2{font-size:21px!important}.sodium-mail-content h3{font-size:18px!important}}'
        . '</style></head><body><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#f1f5f9"><tr><td align="center" style="padding:16px 8px"><table role="presentation" class="sodium-mail-shell" width="680" cellpadding="0" cellspacing="0" style="width:100%;max-width:680px;background:#ffffff"><tr><td class="sodium-mail-content" style="padding:28px 30px;font-family:Arial,sans-serif;color:#1f2937;font-size:15px;line-height:1.55">'
        . $rendered
        . '</td></tr></table></td></tr></table></body></html>';
}

function document_pdf_token(int $documentId): string
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT token FROM document_pdf_tokens WHERE document_id=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$documentId]);
    $token = (string)($stmt->fetchColumn() ?: '');
    if ($token !== '') return $token;
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO document_pdf_tokens (document_id, token) VALUES (?, ?)')->execute([$documentId, $token]);
    return $token;
}

function mail_public_pdf_url(int $documentId): string
{
    return BASE_URL . '/document-pdf.php?token=' . document_pdf_token($documentId);
}

function mail_queue_create(array $data): int
{
    global $pdo;
    mail_ensure_schema();
    $accountId = (int)($data['account_id'] ?? 0) ?: system_mail_account_id();
    $templateId = (int)($data['template_id'] ?? 0) ?: system_mail_template_id();
    $template = null;
    if ($templateId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM mail_templates WHERE id=?');
        $stmt->execute([$templateId]);
        $template = $stmt->fetch() ?: null;
    }
    $subject = trim((string)($data['subject'] ?? '')) ?: 'Message Sodium';
    $content = (string)($data['content_html'] ?? '');
    $final = mail_render_template($template, $content, $subject, is_array($data['template_vars'] ?? null) ? $data['template_vars'] : []);
    $stmt = $pdo->prepare('INSERT INTO mail_queue (account_id, template_id, related_type, related_id, to_email, to_name, subject, content_html, final_html, status, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $accountId ?: null,
        $templateId ?: null,
        $data['related_type'] ?? null,
        (int)($data['related_id'] ?? 0) ?: null,
        trim((string)$data['to_email']),
        trim((string)($data['to_name'] ?? '')) ?: null,
        $subject,
        $content,
        $final,
        $data['status'] ?? 'queued',
        $data['scheduled_at'] ?? date('Y-m-d H:i:s'),
        (int)(current_user()['id'] ?? 0) ?: null,
    ]);
    return (int)$pdo->lastInsertId();
}

function password_reset_ensure_schema(): void
{
    static $ready = false;
    if ($ready) return;
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (
        user_id INT NOT NULL PRIMARY KEY,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_attempt_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function password_reset_mail_account_id(): int
{
    global $pdo;
    mail_ensure_schema();
    $configuredId = system_mail_account_id();
    $stmt = $pdo->prepare("SELECT id FROM sodium_mail_accounts WHERE id=? AND account_status='active' AND password_cipher IS NOT NULL AND password_cipher<>'' LIMIT 1");
    $stmt->execute([$configuredId]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function system_mail_transport_available(): bool
{
    global $pdo;
    $transport=function_exists('sodium_system_mail_transport')?sodium_system_mail_transport():'smtp';
    $settings=function_exists('sodium_instance_settings')?sodium_instance_settings():[];
    if($transport==='smtp')return password_reset_mail_account_id()>0;
    if($transport==='brevo')return function_exists('curl_init')&&function_exists('sodium_system_brevo_api_key')&&sodium_system_brevo_api_key()!==''&&filter_var((string)($settings['system_brevo_from_email']??''),FILTER_VALIDATE_EMAIL)!==false;
    return $transport==='php'&&function_exists('mail')&&filter_var(function_exists('sodium_system_sender_email')?sodium_system_sender_email():'',FILTER_VALIDATE_EMAIL)!==false;
}

function system_mail_apply_transport(array $row): array
{
    $transport=function_exists('sodium_system_mail_transport')?sodium_system_mail_transport():'smtp';
    $settings=function_exists('sodium_instance_settings')?sodium_instance_settings():[];
    if($transport==='brevo'){
        $row['provider']='brevo';$row['brevo_api_key']=sodium_system_brevo_api_key();$row['from_email']=(string)($settings['system_brevo_from_email']??'');$row['from_name']=(string)($settings['system_sender_name']??'Sodium');$row['reply_to']=$row['from_email'];
    }elseif($transport==='php'){
        $row['provider']='php';$row['from_email']=function_exists('sodium_system_sender_email')?sodium_system_sender_email():'no-reply@localhost';$row['from_name']=(string)($settings['system_sender_name']??'Sodium');$row['reply_to']=$row['from_email'];
    }
    return $row;
}

function password_reset_request_code(string $email, string $universe): bool
{
    global $pdo;
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    password_reset_ensure_schema();
    mail_ensure_schema();
    if (!system_mail_transport_available()) throw new RuntimeException('Aucun moyen d’envoi système n’est configuré. La procédure de mot de passe perdu est impossible pour le moment.');
    $stmt = $pdo->prepare("SELECT id,username,first_name,last_name,email,professional_email
        FROM users WHERE account_status='active' AND (LOWER(email)=? OR LOWER(professional_email)=?) LIMIT 1");
    $stmt->execute([$email,$email]);
    $user = $stmt->fetch();
    if (!$user) return false;

    $recent = $pdo->prepare("SELECT COUNT(*) FROM mail_queue
        WHERE related_type='password_reset' AND related_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 2 MINUTE)");
    $recent->execute([(int)$user['id']]);
    if ((int)$recent->fetchColumn() > 0) return true;

    $accountId = password_reset_mail_account_id();
    $templateId = system_mail_template_id();
    $templateStmt = $pdo->prepare('SELECT * FROM mail_templates WHERE id=? AND is_active=1 LIMIT 1');
    $templateStmt->execute([$templateId]);
    $template = $templateStmt->fetch();
    if (!$template) throw new RuntimeException('Aucun template actif configuré pour la réinitialisation.');
    $code = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    $hash = password_hash($code,PASSWORD_DEFAULT);
    $universe = 'Sodium';
    $recipient = $email;
    $name = trim((string)($user['first_name']??'').' '.(string)($user['last_name']??'')) ?: (string)$user['username'];
    $safeName = htmlspecialchars($name,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $safeUniverse = htmlspecialchars($universe,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $content = '<p>Bonjour '.$safeName.',</p>'
        .'<p>Voici votre code de réinitialisation pour <strong>'.$safeUniverse.'</strong> :</p>'
        .'<p style="margin:24px 0;text-align:center"><span style="display:inline-block;padding:14px 22px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:10px;font-family:monospace;font-size:28px;font-weight:700;letter-spacing:6px;color:#0f172a">'.$code.'</span></p>'
        .'<p>Ce code est valable pendant <strong>1 heure</strong>. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>';

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_reset_hash=?,password_reset_expires_at=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE id=?')
            ->execute([$hash,(int)$user['id']]);
        $pdo->prepare('INSERT INTO password_reset_attempts (user_id,attempts,requested_at,last_attempt_at)
            VALUES (?,0,NOW(),NULL) ON DUPLICATE KEY UPDATE attempts=0,requested_at=NOW(),last_attempt_at=NULL')
            ->execute([(int)$user['id']]);
        $subject='['.$universe.'] Votre code de réinitialisation';
        $finalHtml=mail_render_template($template,$content,$subject,['year'=>date('Y')]);
        $queueStatus=defined('PASSWORD_RESET_TEST_MODE') && PASSWORD_RESET_TEST_MODE ? 'test' : 'queued';
        $pdo->prepare('INSERT INTO mail_queue
            (account_id,template_id,related_type,related_id,to_email,to_name,subject,content_html,final_html,status,scheduled_at,created_by)
            VALUES (?,?,\'password_reset\',?,?,?,?,?,?,?,NOW(),NULL)')
            ->execute([$accountId?:null,$templateId,(int)$user['id'],$recipient,$name,$subject,$content,$finalHtml,$queueStatus]);
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function password_reset_complete(string $email, string $code, string $newPassword): array
{
    global $pdo;
    $email = mb_strtolower(trim($email));
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/',$code)) {
        return ['ok'=>false,'message'=>'Code invalide ou expiré.'];
    }
    if (mb_strlen($newPassword) < 10) {
        return ['ok'=>false,'message'=>'Le nouveau mot de passe doit contenir au moins 10 caractères.'];
    }
    password_reset_ensure_schema();
    $stmt = $pdo->prepare("SELECT id,password_reset_hash,password_reset_expires_at
        FROM users WHERE account_status='active' AND (LOWER(email)=? OR LOWER(professional_email)=?) LIMIT 1");
    $stmt->execute([$email,$email]);
    $user = $stmt->fetch();
    if (!$user) return ['ok'=>false,'message'=>'Code invalide ou expiré.'];
    $attemptStmt = $pdo->prepare('SELECT attempts FROM password_reset_attempts WHERE user_id=?');
    $attemptStmt->execute([(int)$user['id']]);
    $attempts = (int)($attemptStmt->fetchColumn() ?: 0);
    $notExpired = !empty($user['password_reset_expires_at']) && strtotime((string)$user['password_reset_expires_at']) >= time();
    $valid = $attempts < 5 && $notExpired && !empty($user['password_reset_hash'])
        && password_verify($code,(string)$user['password_reset_hash']);
    if (!$valid) {
        $pdo->prepare('INSERT INTO password_reset_attempts (user_id,attempts,requested_at,last_attempt_at)
            VALUES (?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE attempts=LEAST(255,attempts+1),last_attempt_at=NOW()')
            ->execute([(int)$user['id']]);
        return ['ok'=>false,'message'=>'Code invalide ou expiré.'];
    }
    ensure_user_sessions_table();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash=?,password_reset_hash=NULL,password_reset_expires_at=NULL,updated_at=NOW() WHERE id=?')
            ->execute([password_hash($newPassword,PASSWORD_DEFAULT),(int)$user['id']]);
        $pdo->prepare('DELETE FROM password_reset_attempts WHERE user_id=?')->execute([(int)$user['id']]);
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id=?')->execute([(int)$user['id']]);
        $pdo->commit();
        return ['ok'=>true,'message'=>'Votre mot de passe a été modifié.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}


function mail_mark_failed(int $queueId, string $message): void
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE mail_queue SET status='failed', error_message=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([mb_substr($message, 0, 5000), $queueId]);
}

function mail_process_queue(int $limit = 20): array
{
    global $pdo;
    mail_ensure_schema();
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query("SELECT q.*, 'smtp' provider, a.display_name account_name, a.email_address from_email, a.display_name from_name, a.email_address reply_to, a.smtp_host, a.smtp_port, COALESCE(NULLIF(a.login_name,''),a.email_address) smtp_username, a.password_cipher smtp_password, a.smtp_encryption, NULL brevo_api_key, 1 sodium_password_cipher
        FROM mail_queue q
        LEFT JOIN sodium_mail_accounts a ON a.id=q.account_id
        WHERE q.status='queued' AND (q.scheduled_at IS NULL OR q.scheduled_at <= NOW())
        ORDER BY q.scheduled_at ASC, q.id ASC
        LIMIT {$limit}");
    $rows = $stmt->fetchAll();
    $done = ['sent' => 0, 'failed' => 0];
    foreach ($rows as $row) {
        $row=system_mail_apply_transport($row);
        $queueId = (int)$row['id'];
        try {
            $pdo->prepare("UPDATE mail_queue SET status='sending', error_message=NULL, updated_at=NOW() WHERE id=? AND status='queued'")->execute([$queueId]);
            mail_send_queued_row($row);
            $pdo->prepare("UPDATE mail_queue SET status='sent', sent_at=NOW(), error_message=NULL, updated_at=NOW() WHERE id=?")->execute([$queueId]);
            $done['sent']++;
        } catch (Throwable $e) {
            mail_mark_failed($queueId, $e->getMessage());
            $done['failed']++;
        }
    }
    return $done;
}

function mail_send_queued_row(array $row): void
{
    $provider = (string)($row['provider'] ?? '');
    if ($provider === 'brevo') {
        mail_send_brevo($row);
        return;
    }
    if ($provider === 'php') {
        mail_send_php($row);
        return;
    }
    mail_send_smtp($row);
}

function mail_send_php(array $row): void
{
    $fromEmail=trim((string)($row['from_email']??''));$toEmail=trim((string)($row['to_email']??''));
    if(!filter_var($fromEmail,FILTER_VALIDATE_EMAIL)||!filter_var($toEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Adresse d’envoi PHP invalide.');
    $fromName=trim((string)($row['from_name']??'Sodium'));
    $headers=['MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','Content-Transfer-Encoding: 8bit','From: '.mb_encode_mimeheader($fromName).' <'.$fromEmail.'>','Reply-To: <'.$fromEmail.'>'];
    if(!mail($toEmail,mb_encode_mimeheader((string)$row['subject']),(string)$row['final_html'],implode("\r\n",$headers)))throw new RuntimeException('La fonction PHP mail() a refusé le message.');
}

function mail_send_brevo(array $row): void
{
    if (empty($row['brevo_api_key'])) {
        throw new RuntimeException('Clé API Brevo manquante.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Extension cURL indisponible pour Brevo.');
    }
    $fromEmail = (string)$row['from_email'];
    $toEmail = (string)$row['to_email'];
    $senderName = trim((string)($row['from_name'] ?: $row['account_name'] ?: $fromEmail));
    $toName = trim((string)($row['to_name'] ?? ''));
    if ($toName === '') {
        $toName = $toEmail;
    }
    $payload = [
        'sender' => ['email' => $fromEmail, 'name' => $senderName],
        'to' => [['email' => $toEmail, 'name' => $toName]],
        'subject' => (string)$row['subject'],
        'htmlContent' => (string)$row['final_html'],
    ];
    if (!empty($row['reply_to'])) {
        $payload['replyTo'] = ['email' => (string)$row['reply_to']];
    }
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . (string)$row['brevo_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Erreur Brevo (' . $status . ') ' . ($error ?: (string)$body));
    }
}

function mail_smtp_read($socket): string
{
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
}

function mail_smtp_cmd($socket, string $cmd, array $expected): string
{
    fwrite($socket, $cmd . "\r\n");
    $reply = mail_smtp_read($socket);
    $code = (int)substr($reply, 0, 3);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('Réponse SMTP inattendue: ' . trim($reply));
    }
    return $reply;
}

function mail_smtp_data_escape(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);
    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) $line = '.' . $line;
    }
    return implode("\r\n", $lines);
}

function mail_send_smtp(array $row): void
{
    if(!empty($row['sodium_password_cipher']))$row['smtp_password']=sodium_decrypt_secret((string)($row['smtp_password']??''));
    $host = trim((string)($row['smtp_host'] ?? ''));
    $port = (int)($row['smtp_port'] ?? 0);
    if ($host === '' || empty($row['from_email'])) {
        throw new RuntimeException('Compte SMTP incomplet.');
    }
    $encryption = strtolower((string)($row['smtp_encryption'] ?? ''));
    $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $socket = @stream_socket_client($target . ':' . ($port ?: ($encryption === 'ssl' ? 465 : 587)), $errno, $errstr, 30);
    if (!$socket) {
        throw new RuntimeException('Connexion SMTP impossible: ' . $errstr);
    }
    stream_set_timeout($socket, 30);
    try {
        mail_smtp_read($socket);
        mail_smtp_cmd($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        if ($encryption === 'tls') {
            mail_smtp_cmd($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Activation TLS impossible.');
            }
            mail_smtp_cmd($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }
        if (!empty($row['smtp_username'])) {
            mail_smtp_cmd($socket, 'AUTH LOGIN', [334]);
            mail_smtp_cmd($socket, base64_encode((string)$row['smtp_username']), [334]);
            mail_smtp_cmd($socket, base64_encode((string)($row['smtp_password'] ?? '')), [235]);
        }
        mail_smtp_cmd($socket, 'MAIL FROM:<' . (string)$row['from_email'] . '>', [250]);
        mail_smtp_cmd($socket, 'RCPT TO:<' . (string)$row['to_email'] . '>', [250, 251]);
        mail_smtp_cmd($socket, 'DATA', [354]);
        $fromName = trim((string)($row['from_name'] ?: $row['account_name']));
        $headers = [
            'From: ' . ($fromName !== '' ? mb_encode_mimeheader($fromName) . ' ' : '') . '<' . (string)$row['from_email'] . '>',
            'To: <' . (string)$row['to_email'] . '>',
            'Subject: ' . mb_encode_mimeheader((string)$row['subject']),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        if (!empty($row['reply_to'])) {
            $headers[] = 'Reply-To: <' . (string)$row['reply_to'] . '>';
        }
        $message = implode("\r\n", $headers) . "\r\n\r\n" . (string)$row['final_html'];
        mail_smtp_cmd($socket, mail_smtp_data_escape($message) . "\r\n.", [250]);
        mail_smtp_cmd($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
