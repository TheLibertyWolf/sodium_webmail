<?php
declare(strict_types=1);

const SODIUM_APTITUDES = [
    'sodium_full_access' => 'Accès complet',
    'licence' => 'Licence',
    'sodium_signatures_view' => 'Signatures — consultation',
    'sodium_signatures_manage_my' => 'Signatures — gestion personnelle',
    'sodium_signatures_manage_full' => 'Signatures — gestion complète',
    'sodium_labels_view' => 'Tags — consultation',
    'sodium_labels_manage_my' => 'Tags — gestion personnelle',
    'sodium_labels_manage_full' => 'Tags — gestion complète',
    'sodium_templates_view' => 'Modèles — consultation',
    'sodium_templates_manage_my' => 'Modèles — gestion personnelle',
    'sodium_templates_manage_full' => 'Modèles — gestion complète',
    'sodium_accounts_view' => 'Comptes mails — consultation',
    'sodium_accounts_manage' => 'Comptes mails — gestion',
    'sodium_settings_view' => 'Messages — consultation',
    'sodium_settings_manage' => 'Messages — gestion',
];

function sodium_ensure_schema(): void
{
    static $ready = false;
    if ($ready) return;
    global $pdo;

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_mail_accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email_address VARCHAR(190) NOT NULL UNIQUE,
        display_name VARCHAR(190) NOT NULL DEFAULT '',
        imap_host VARCHAR(190) NOT NULL DEFAULT 'localhost',
        imap_port SMALLINT UNSIGNED NOT NULL DEFAULT 993,
        imap_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
        smtp_host VARCHAR(190) NOT NULL DEFAULT 'localhost',
        smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 465,
        smtp_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
        login_name VARCHAR(190) NOT NULL DEFAULT '',
        account_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        folder_cache_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sodium_mail_status (account_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_user_mail_accounts (
        user_id INT NOT NULL,
        mail_account_id INT UNSIGNED NOT NULL,
        can_read TINYINT(1) NOT NULL DEFAULT 1,
        can_send TINYINT(1) NOT NULL DEFAULT 1,
        can_manage TINYINT(1) NOT NULL DEFAULT 0,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, mail_account_id),
        INDEX idx_sodium_access_account (mail_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_user_aptitudes (
        user_id INT NOT NULL,
        label VARCHAR(80) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, label),
        INDEX idx_sodium_aptitude_label (label)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_app_license (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        license_key_cipher TEXT NULL,
        verification_status VARCHAR(20) NOT NULL DEFAULT 'missing',
        license_type VARCHAR(60) NULL,
        rights_holder VARCHAR(190) NULL,
        allowed_domain VARCHAR(190) NULL,
        expires_at DATETIME NULL,
        registered_at DATETIME NULL,
        product_name VARCHAR(190) NULL,
        product_slug VARCHAR(100) NULL,
        last_checked_at DATETIME NULL,
        last_message VARCHAR(500) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO sodium_app_license (id) VALUES (1)");
    $pdo->exec("INSERT IGNORE INTO sodium_user_aptitudes (user_id,label)
        SELECT user_id,'sodium_accounts_view' FROM sodium_user_aptitudes WHERE label IN ('sodium_accounts_access','sodium_accounts_manage')");
    $pdo->exec("INSERT IGNORE INTO sodium_user_aptitudes (user_id,label) VALUES (1,'sodium_full_access'),(1,'licence')");
    $pdo->exec("DELETE FROM sodium_user_aptitudes WHERE label IN ('show_id','show_ip')");
    $pdo->exec("INSERT IGNORE INTO sodium_user_aptitudes (user_id,label)
        SELECT user_id,'sodium_settings_view' FROM sodium_user_aptitudes WHERE label='sodium_settings_access'");
    $pdo->exec("INSERT IGNORE INTO sodium_user_aptitudes (user_id,label)
        SELECT user_id,'sodium_signatures_manage_my' FROM sodium_user_aptitudes WHERE label='sodium_signatures_manage'");
    $pdo->exec("INSERT IGNORE INTO sodium_user_aptitudes (user_id,label)
        SELECT user_id,'sodium_labels_manage_my' FROM sodium_user_aptitudes WHERE label='sodium_labels_manage'");
    $pdo->exec("DELETE FROM sodium_user_aptitudes WHERE label IN
        ('sodium_users_access','sodium_users_manage','sodium_accounts_access','sodium_settings_access',
         'sodium_signatures_manage','sodium_labels_manage')");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_user_settings (
        user_id INT NOT NULL PRIMARY KEY,
        refresh_interval SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        send_delay SMALLINT UNSIGNED NOT NULL DEFAULT 10,
        quote_reply TINYINT(1) NOT NULL DEFAULT 1,
        signature_position ENUM('before_quote','after_quote') NOT NULL DEFAULT 'before_quote',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    sodium_ensure_column('sodium_user_settings', 'send_delay', 'SMALLINT UNSIGNED NOT NULL DEFAULT 10');

    sodium_ensure_column('sodium_mail_accounts', 'password_cipher', 'TEXT NULL');
    sodium_ensure_column('sodium_mail_accounts', 'label_text', "VARCHAR(60) NOT NULL DEFAULT ''");
    sodium_ensure_column('sodium_mail_accounts', 'label_color', "VARCHAR(20) NOT NULL DEFAULT '#dc3545'");
    sodium_ensure_column('sodium_mail_accounts', 'icon_path', 'VARCHAR(500) NULL');
    sodium_ensure_column('sodium_mail_accounts', 'unread_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
    sodium_ensure_column('sodium_mail_accounts', 'quota_used_kb', 'BIGINT UNSIGNED NULL');
    sodium_ensure_column('sodium_mail_accounts', 'quota_limit_kb', 'BIGINT UNSIGNED NULL');
    sodium_ensure_column('sodium_mail_accounts', 'last_sync_at', 'DATETIME NULL');
    sodium_ensure_column('sodium_mail_accounts', 'last_error', 'VARCHAR(1000) NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_signatures (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mail_account_id INT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        content_html MEDIUMTEXT NOT NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sodium_signatures_user_account (user_id, mail_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    sodium_ensure_column('sodium_signatures', 'sender_name', "VARCHAR(190) NOT NULL DEFAULT ''");
    sodium_ensure_column('sodium_signatures', 'is_shared', 'TINYINT(1) NOT NULL DEFAULT 0');

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_tags (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mail_account_id INT UNSIGNED NOT NULL,
        name VARCHAR(80) NOT NULL,
        color VARCHAR(20) NOT NULL DEFAULT '#6c757d',
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sodium_tag_account_name (mail_account_id, name),
        INDEX idx_sodium_tags_account (mail_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    sodium_ensure_column('sodium_tags', 'shared_key', "CHAR(32) NOT NULL DEFAULT ''");
    sodium_ensure_column('sodium_tags', 'applies_all', 'TINYINT(1) NOT NULL DEFAULT 0');
    sodium_ensure_column('sodium_tags', 'is_shared', 'TINYINT(1) NOT NULL DEFAULT 1');

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_tag_templates (
        shared_key CHAR(32) PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        color VARCHAR(20) NOT NULL DEFAULT '#6c757d',
        applies_all TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    sodium_ensure_column('sodium_tag_templates', 'is_shared', 'TINYINT(1) NOT NULL DEFAULT 1');
    $pdo->exec("UPDATE sodium_tags SET shared_key=MD5(CONCAT('legacy|',id,'|',created_at)) WHERE shared_key=''");
    $pdo->exec("INSERT IGNORE INTO sodium_tag_templates (shared_key,name,color,applies_all,created_by,created_at)
        SELECT shared_key,name,color,applies_all,created_by,created_at FROM sodium_tags WHERE shared_key<>''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_message_tags (
        mail_account_id INT UNSIGNED NOT NULL,
        message_key CHAR(64) NOT NULL,
        tag_id INT UNSIGNED NOT NULL,
        tagged_by INT NULL,
        tagged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (mail_account_id, message_key, tag_id),
        INDEX idx_sodium_message_tags_tag (tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_message_replies (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mail_account_id INT UNSIGNED NOT NULL,
        source_message_key CHAR(64) NOT NULL,
        user_id INT NOT NULL,
        replied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sodium_replies_message (mail_account_id, source_message_key),
        INDEX idx_sodium_replies_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    sodium_ensure_column('sodium_message_replies', 'subject', "VARCHAR(998) NOT NULL DEFAULT ''");
    sodium_ensure_column('sodium_message_replies', 'content_html', 'LONGTEXT NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_message_list_cache (
        mail_account_id INT UNSIGNED NOT NULL,
        message_key CHAR(64) NOT NULL,
        has_attachment TINYINT(1) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (mail_account_id, message_key),
        INDEX idx_sodium_message_list_cache_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_contacts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mail_account_id INT UNSIGNED NOT NULL,
        email VARCHAR(190) NOT NULL,
        display_name VARCHAR(190) NOT NULL DEFAULT '',
        source VARCHAR(30) NOT NULL DEFAULT 'mail',
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sodium_contact_account_email (mail_account_id, email),
        INDEX idx_sodium_contacts_lookup (mail_account_id, display_name, email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_contact_index_state (
        mail_account_id INT UNSIGNED NOT NULL,
        folder_key VARCHAR(255) NOT NULL,
        last_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,
        indexed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (mail_account_id, folder_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_remote_image_senders (
        user_id INT NOT NULL,
        sender_email VARCHAR(190) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, sender_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_composed_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mail_account_id INT UNSIGNED NOT NULL,
        status ENUM('draft','scheduled','sent','failed') NOT NULL DEFAULT 'draft',
        to_json LONGTEXT NOT NULL,
        cc_json LONGTEXT NOT NULL,
        bcc_json LONGTEXT NOT NULL,
        subject VARCHAR(998) NOT NULL DEFAULT '',
        content_html LONGTEXT NOT NULL,
        signature_id INT UNSIGNED NULL,
        priority ENUM('normal','high','low') NOT NULL DEFAULT 'normal',
        attachments_json LONGTEXT NULL,
        scheduled_at DATETIME NULL,
        sent_at DATETIME NULL,
        last_error VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sodium_composed_due (status, scheduled_at),
        INDEX idx_sodium_composed_user (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    sodium_ensure_column('sodium_composed_messages', 'reply_account_id', 'INT UNSIGNED NULL');
    sodium_ensure_column('sodium_composed_messages', 'reply_message_key', 'CHAR(64) NULL');
    sodium_ensure_column('sodium_composed_messages', 'in_reply_to', 'VARCHAR(998) NULL');
    sodium_ensure_column('sodium_composed_messages', 'undo_until', 'DATETIME NULL');
    sodium_ensure_column('sodium_composed_messages', 'edit_original_scheduled_at', 'DATETIME NULL');
    sodium_ensure_column('sodium_composed_messages', 'edit_original_undo_until', 'DATETIME NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_temp_uploads (
        token CHAR(64) PRIMARY KEY,
        user_id INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(190) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL,
        storage_name VARCHAR(190) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        INDEX idx_sodium_temp_user (user_id,expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_reply_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mail_account_id INT UNSIGNED NULL,
        name VARCHAR(190) NOT NULL,
        subject VARCHAR(998) NOT NULL DEFAULT '',
        content_html LONGTEXT NOT NULL,
        is_shared TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sodium_templates_scope (mail_account_id,user_id,is_shared)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_auto_replies (
        mail_account_id INT UNSIGNED PRIMARY KEY,
        user_id INT NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        subject VARCHAR(998) NOT NULL DEFAULT 'Réponse automatique',
        content_html LONGTEXT NOT NULL,
        last_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_auto_reply_rules (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(190) NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        applies_all TINYINT(1) NOT NULL DEFAULT 0,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        subject VARCHAR(998) NOT NULL DEFAULT 'Réponse automatique',
        content_html LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sodium_auto_rules_active (enabled,starts_at,ends_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_auto_reply_rule_accounts (
        rule_id INT UNSIGNED NOT NULL,
        mail_account_id INT UNSIGNED NOT NULL,
        last_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY(rule_id,mail_account_id),
        INDEX idx_sodium_auto_rule_account (mail_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_push_subscriptions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        endpoint_hash CHAR(64) NOT NULL UNIQUE,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sodium_push_user (user_id,enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_instance_settings(setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    sodium_ensure_column('users','twofa_required','TINYINT(1) NOT NULL DEFAULT 0');
    sodium_ensure_column('users','twofa_enabled','TINYINT(1) NOT NULL DEFAULT 0');
    sodium_ensure_column('users','twofa_secret','VARCHAR(100) NULL');
    sodium_ensure_column('users','password_reset_hash','VARCHAR(255) NULL');
    sodium_ensure_column('users','password_reset_expires_at','DATETIME NULL');
    sodium_ensure_column('users','sodium_personal_account_limit','INT UNSIGNED NOT NULL DEFAULT 0');
    sodium_ensure_column('users','sodium_personal_excluded_domains','TEXT NULL');
    sodium_ensure_column('users','sodium_personal_excluded_addresses','TEXT NULL');
    sodium_ensure_column('sodium_mail_accounts','created_by_user_id','INT NULL');
    sodium_ensure_column('sodium_mail_accounts','is_user_managed','TINYINT(1) NOT NULL DEFAULT 0');
    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_banned_mail_addresses(email_address VARCHAR(190) PRIMARY KEY,banned_by_user_id INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function sodium_instance_settings():array{global $pdo;sodium_ensure_schema();$defaults=['instance_name'=>'Sodium','organization_name'=>'','support_email'=>'','system_mail_account_id'=>'0','system_sender_name'=>'Sodium','system_mail_transport'=>'php','system_brevo_api_cipher'=>'','system_brevo_from_email'=>'','timezone'=>'Europe/Paris','cron_last_run_at'=>'','cron_last_status'=>'never','turnstile_enabled'=>(defined('TURNSTILE_ENABLED')&&TURNSTILE_ENABLED?'1':'0'),'turnstile_site_key'=>(defined('TURNSTILE_SITE_KEY')?TURNSTILE_SITE_KEY:''),'turnstile_secret_cipher'=>''];$rows=$pdo->query('SELECT setting_key,setting_value FROM sodium_instance_settings')->fetchAll(PDO::FETCH_KEY_PAIR);return array_merge($defaults,$rows?:[]);}
function sodium_save_instance_settings(array $settings):void{global $pdo;sodium_ensure_schema();$stmt=$pdo->prepare('INSERT INTO sodium_instance_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($settings as $key=>$value)$stmt->execute([$key,(string)$value]);}
function sodium_turnstile_enabled():bool{return (string)(sodium_instance_settings()['turnstile_enabled']??'0')==='1';}
function sodium_turnstile_site_key():string{return trim((string)(sodium_instance_settings()['turnstile_site_key']??''));}
function sodium_turnstile_secret_key():string{$cipher=(string)(sodium_instance_settings()['turnstile_secret_cipher']??'');if($cipher!==''){try{return sodium_decrypt_secret($cipher);}catch(Throwable){return'';}}return defined('TURNSTILE_SECRET_KEY')?(string)TURNSTILE_SECRET_KEY:'';}
function sodium_system_mail_transport():string{$transport=(string)(sodium_instance_settings()['system_mail_transport']??'php');return in_array($transport,['smtp','brevo','php'],true)?$transport:'php';}
function sodium_system_brevo_api_key():string{$cipher=(string)(sodium_instance_settings()['system_brevo_api_cipher']??'');if($cipher==='')return'';try{return sodium_decrypt_secret($cipher);}catch(Throwable){return'';}}
function sodium_system_sender_email():string{global $pdo;$settings=sodium_instance_settings();$id=(int)$settings['system_mail_account_id'];if($id){$stmt=$pdo->prepare('SELECT email_address FROM sodium_mail_accounts WHERE id=?');$stmt->execute([$id]);$email=(string)($stmt->fetchColumn()?:'');if(filter_var($email,FILTER_VALIDATE_EMAIL))return$email;}return'no-reply@'.(defined('SODIUM_LICENSE_DOMAIN')?SODIUM_LICENSE_DOMAIN:'localhost');}

function sodium_ensure_column(string $table, string $column, string $definition): void
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    if (!(int) $stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function sodium_temp_upload_directory(): string
{
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sodium-' . substr(hash('sha256', __DIR__), 0, 16);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Stockage temporaire indisponible.');
    return $directory;
}

function sodium_cleanup_temp_uploads(): void
{
    global $pdo;
    $stmt=$pdo->query('SELECT token,storage_name FROM sodium_temp_uploads WHERE expires_at<NOW() LIMIT 200');
    foreach($stmt->fetchAll() as $row){@unlink(sodium_temp_upload_directory().DIRECTORY_SEPARATOR.basename((string)$row['storage_name']));$pdo->prepare('DELETE FROM sodium_temp_uploads WHERE token=?')->execute([(string)$row['token']]);}
}

function sodium_take_temp_uploads(array $tokens, int $userId): array
{
    global $pdo;
    $files=[];$total=0;
    $stmt=$pdo->prepare('SELECT * FROM sodium_temp_uploads WHERE token=? AND user_id=? AND expires_at>=NOW()');
    foreach(array_slice(array_values(array_unique($tokens)),0,30) as $token){
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))continue;
        $stmt->execute([$token,$userId]);$row=$stmt->fetch();if(!$row)continue;
        $path=sodium_temp_upload_directory().DIRECTORY_SEPARATOR.basename((string)$row['storage_name']);if(!is_file($path))continue;
        $total+=(int)$row['file_size'];if($total>25*1024*1024)throw new RuntimeException('Les pièces jointes dépassent 25 Mo au total.');
        $files[]=['name'=>(string)$row['original_name'],'type'=>(string)$row['mime_type'],'data'=>(string)file_get_contents($path),'_token'=>$token,'_path'=>$path];
    }
    return $files;
}

function sodium_delete_temp_uploads(array $files): void
{
    global $pdo;
    $stmt=$pdo->prepare('DELETE FROM sodium_temp_uploads WHERE token=?');
    foreach($files as $file){if(empty($file['_token']))continue;@unlink((string)$file['_path']);$stmt->execute([(string)$file['_token']]);}
}

function sodium_secret_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $path = dirname(__DIR__) . '/.sodium-mail-key';
    $hex = is_readable($path) ? trim((string) file_get_contents($path)) : '';
    if (!preg_match('/^[a-f0-9]{64}$/i', $hex)) {
        throw new RuntimeException('Clé de chiffrement Sodium indisponible.');
    }
    return $key = hex2bin($hex);
}

function sodium_encrypt_secret(string $plain): string
{
    if ($plain === '') return '';
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', sodium_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Chiffrement du mot de passe impossible.');
    return base64_encode($iv . $tag . $cipher);
}

function sodium_decrypt_secret(?string $encoded): string
{
    if (!$encoded) return '';
    $payload = base64_decode($encoded, true);
    if ($payload === false || strlen($payload) < 29) return '';
    $plain = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', sodium_secret_key(), OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16));
    return $plain === false ? '' : $plain;
}

function sodium_user_is_admin(?array $user = null): bool
{
    $user ??= current_user();
    return ($user['role'] ?? '') === 'admin';
}

function sodium_user_aptitudes(?int $userId = null): array
{
    global $pdo;
    sodium_ensure_schema();
    $userId ??= (int) (current_user()['id'] ?? 0);
    if (!$userId) return [];
    $stmt = $pdo->prepare('SELECT label FROM sodium_user_aptitudes WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'label');
}

function sodium_can(string $aptitude, ?array $user = null): bool
{
    $user ??= current_user();
    if (!$user) return false;
    if (sodium_user_is_admin($user)) return true;
    $aptitudes = sodium_user_aptitudes((int) $user['id']);
    if($aptitude==='licence')return in_array('licence',$aptitudes,true);
    if (in_array('sodium_full_access', $aptitudes, true) || in_array($aptitude, $aptitudes, true)) return true;
    if (str_ends_with($aptitude, '_view')) {
        $base = substr($aptitude, 0, -5);
        return in_array($base . '_manage', $aptitudes, true)
            || in_array($base . '_manage_my', $aptitudes, true)
            || in_array($base . '_manage_full', $aptitudes, true);
    }
    return false;
}

function sodium_license_record(): array
{
    global $pdo;
    sodium_ensure_schema();
    $row=$pdo->query('SELECT * FROM sodium_app_license WHERE id=1')->fetch();
    return $row?:['id'=>1,'verification_status'=>'missing'];
}

function sodium_license_public_info(): array
{
    $row=sodium_license_record();
    $configured=!empty($row['license_key_cipher']);
    unset($row['license_key_cipher']);
    $row['is_configured']=$configured;
    $row['is_valid']=$row['is_configured']&&($row['verification_status']??'')==='ok'
        && (empty($row['expires_at'])||strtotime((string)$row['expires_at'])>=time());
    return $row;
}

function sodium_verify_license_key(string $licenseKey): array
{
    if(!preg_match('/^[a-f0-9]{128}$/',strtolower($licenseKey)))return ['status'=>'invalid','message'=>'Format de clé invalide.'];
    if(!function_exists('curl_init'))return ['status'=>'invalid','message'=>'Service de vérification indisponible.'];
    $curl=curl_init('https://licence.jessysystem.com/');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode(['license_key'=>strtolower($licenseKey),'product_slug'=>defined('SODIUM_LICENSE_PRODUCT_SLUG')?SODIUM_LICENSE_PRODUCT_SLUG:'sodium-webmail','domain'=>defined('SODIUM_LICENSE_DOMAIN')?SODIUM_LICENSE_DOMAIN:strtolower((string)($_SERVER['HTTP_HOST']??'')),'version'=>'0.9.4'])]);
    $body=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
    if($body===false||$code!==200)return ['status'=>'invalid','message'=>$error!==''?'Serveur de licences inaccessible.':'Réponse invalide du serveur de licences.'];
    $result=json_decode((string)$body,true);
    return is_array($result)?$result:['status'=>'invalid','message'=>'Réponse de licence illisible.'];
}

function sodium_store_license(string $licenseKey,array $verification): void
{
    global $pdo;
    sodium_ensure_schema();
    $stmt=$pdo->prepare('UPDATE sodium_app_license SET license_key_cipher=?,verification_status=?,license_type=?,rights_holder=?,allowed_domain=?,expires_at=?,registered_at=?,product_name=?,product_slug=?,last_checked_at=NOW(),last_message=? WHERE id=1');
    $stmt->execute([sodium_encrypt_secret(strtolower($licenseKey)),(string)($verification['status']??'invalid'),$verification['license_type']??null,$verification['rights_holder']??($verification['owner_name']??null),$verification['allowed_domain']??null,$verification['expires_at']??null,$verification['registered_at']??null,$verification['product_name']??'Sodium Webmail',$verification['product_slug']??'sodium-webmail',$verification['message']??null]);
}

function sodium_license_is_valid(bool $refresh=false): bool
{
    global $pdo;
    $row=sodium_license_record();
    $cipher=(string)($row['license_key_cipher']??'');
    if($cipher==='')return false;
    $fresh=!empty($row['last_checked_at'])&&strtotime((string)$row['last_checked_at'])>time()-21600;
    if($refresh||!$fresh){
        $key=sodium_decrypt_secret($cipher);
        $result=sodium_verify_license_key($key);
        sodium_store_license($key,$result);
        $row=sodium_license_record();
    }
    return ($row['verification_status']??'')==='ok'&&(!empty($row['expires_at'])?strtotime((string)$row['expires_at'])>=time():true);
}

function sodium_enforce_license(): void
{
    if(!current_user())return;
    $script=(string)($_SERVER['SCRIPT_NAME']??'');
    if(in_array($script,['/profile.php','/logout.php','/login.php'],true))return;
    if($script==='/admin/license.php')return;
    if(!sodium_license_is_valid())redirect(sodium_can('licence')?'/admin/license.php?required=1':'/profile.php?license_required=1');
}

function sodium_can_manage_own(string $module): bool
{
    return sodium_can($module . '_manage_my') || sodium_can($module . '_manage_full');
}

function sodium_can_manage_all(string $module): bool
{
    return sodium_can($module . '_manage_full');
}

function sodium_user_settings(?int $userId = null): array
{
    global $pdo;
    sodium_ensure_schema();
    $userId ??= (int)(current_user()['id'] ?? 0);
    $defaults = ['refresh_interval'=>1, 'send_delay'=>10, 'quote_reply'=>1, 'signature_position'=>'before_quote'];
    if (!$userId) return $defaults;
    $stmt = $pdo->prepare('SELECT refresh_interval,send_delay,quote_reply,signature_position FROM sodium_user_settings WHERE user_id=?');
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();
    return $settings ? array_merge($defaults, $settings) : $defaults;
}

function sodium_unread_snapshot(?array $accounts = null): array
{
    $accounts ??= sodium_accessible_mail_accounts();
    $total = 0;
    $rows = [];
    foreach ($accounts as $account) {
        $unread = (int)($account['unread_count'] ?? 0);
        $total += $unread;
        $rows[] = ['id'=>(int)$account['id'], 'unread'=>$unread];
    }
    return ['unified_unread'=>$total, 'accounts'=>$rows];
}

function sodium_accessible_mail_accounts(?int $userId = null): array
{
    global $pdo;
    sodium_ensure_schema();
    $userId ??= (int) (current_user()['id'] ?? 0);
    if (!$userId) return [];

    $stmt = $pdo->prepare("SELECT a.*, uma.can_read, uma.can_send, uma.can_manage, uma.is_default
        FROM sodium_mail_accounts a
        INNER JOIN sodium_user_mail_accounts uma ON uma.mail_account_id=a.id
        WHERE uma.user_id=? AND uma.can_read=1 AND a.account_status='active'
        ORDER BY uma.is_default DESC, uma.sort_order, a.display_name, a.email_address");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function sodium_active_mail_account(): ?array
{
    $accounts = sodium_accessible_mail_accounts();
    if (!$accounts) {
        unset($_SESSION['sodium_mail_account_id']);
        return null;
    }

    $requestedId = (int) ($_GET['account_id'] ?? 0);
    $activeId = $requestedId ?: (int) ($_SESSION['sodium_mail_account_id'] ?? 0);
    foreach ($accounts as $account) {
        if ((int) $account['id'] === $activeId) {
            $_SESSION['sodium_mail_account_id'] = $activeId;
            return $account;
        }
    }

    $_SESSION['sodium_mail_account_id'] = (int) $accounts[0]['id'];
    return $accounts[0];
}

function sodium_folder_is_technical(string $key): bool
{
    $normalized=strtolower($key);
    return str_contains($normalized,'.mail.virtual')
        || str_contains($normalized,'dovecot-acl-list')
        || str_contains($normalized,'dovecot_list_index_log')
        || str_contains($normalized,'dovecot.index')
        || str_contains($normalized,'dovecot-uidlist');
}

function sodium_folder_display_label(string $key): string
{
    if(strcasecmp($key,'INBOX')===0)return 'Boîte de réception';
    $label=preg_replace('/^(?:INBOX\.)+/i','',$key)??$key;
    // IMAP utilise une variante d'UTF-7 pour les noms de dossiers. imap_utf8()
    // ne la décode pas correctement avec certaines versions de l'extension.
    try{$label=mb_convert_encoding($label,'UTF-8','UTF7-IMAP');}catch(Throwable){}
    return match(strtolower($label)){
        'sent','sent items','sent messages','envoyés'=>'Envoyés',
        'draft','drafts','brouillons'=>'Brouillons',
        'junk','spam','indésirables'=>'Indésirables',
        'trash','deleted items','corbeille'=>'Corbeille',
        'archive','archives'=>'Archives',
        default=>$label,
    };
}

function sodium_sort_folders(array $folders): array
{
    $priority=static function(array $folder):int{
        $key=strtolower((string)($folder['key']??''));
        $icon=(string)($folder['icon']??sodium_folder_icon($key));
        if($key==='inbox')return 0;
        return match($icon){
            'file-earmark'=>1,
            'send'=>2,
            'archive'=>3,
            'trash'=>4,
            'exclamation-octagon'=>5,
            default=>6,
        };
    };
    usort($folders,static function(array $a,array $b)use($priority):int{
        $rank=$priority($a)<=>$priority($b);
        return $rank!==0?$rank:strnatcasecmp((string)($a['label']??''),(string)($b['label']??''));
    });
    return array_values($folders);
}

function sodium_account_folders(?array $account): array
{
    if (!$account) return [];
    $cached = json_decode((string) ($account['folder_cache_json'] ?? ''), true);
    if (is_array($cached) && $cached) return sodium_sort_folders(array_map(static function(array $folder):array{$folder['label']=sodium_folder_display_label((string)($folder['key']??''));return $folder;},array_filter($cached,static fn(array $folder):bool=>!sodium_folder_is_technical((string)($folder['key']??'')))));
    return sodium_sort_folders([
        ['key' => 'INBOX', 'label' => 'Boîte de réception', 'icon' => 'inbox', 'unread' => 0],
        ['key' => 'Drafts', 'label' => 'Brouillons', 'icon' => 'file-earmark', 'unread' => 0],
        ['key' => 'Sent', 'label' => 'Envoyés', 'icon' => 'send', 'unread' => 0],
        ['key' => 'Junk', 'label' => 'Indésirables', 'icon' => 'exclamation-octagon', 'unread' => 0],
        ['key' => 'Trash', 'label' => 'Corbeille', 'icon' => 'trash', 'unread' => 0],
        ['key' => 'Archive', 'label' => 'Archives', 'icon' => 'archive', 'unread' => 0],
    ]);
}

function sodium_imap_mailbox(array $account, string $folder = ''): string
{
    $security = match ($account['imap_encryption'] ?? 'ssl') {
        'ssl' => '/imap/ssl',
        'tls' => '/imap/tls',
        default => '/imap/notls',
    };
    if (in_array(strtolower((string) $account['imap_host']), ['localhost', '127.0.0.1'], true)) {
        $security .= '/novalidate-cert';
    }
    return '{' . $account['imap_host'] . ':' . (int) $account['imap_port'] . $security . '}' . $folder;
}

function sodium_imap_open_account(array $account, string $folder = 'INBOX', bool $readOnly = true)
{
    $password = sodium_decrypt_secret($account['password_cipher'] ?? '');
    if ($password === '') throw new RuntimeException('Mot de passe du compte mail manquant.');
    imap_timeout(IMAP_OPENTIMEOUT, 8);
    imap_timeout(IMAP_READTIMEOUT, 12);
    $stream = @imap_open(sodium_imap_mailbox($account, $folder), (string) ($account['login_name'] ?: $account['email_address']), $password, $readOnly ? OP_READONLY : 0, 1);
    if (!$stream) {
        $errors = imap_errors() ?: [];
        throw new RuntimeException($errors ? end($errors) : 'Connexion IMAP impossible.');
    }
    return $stream;
}

function sodium_folder_icon(string $key): string
{
    $lower = strtolower($key);
    return match (true) {
        $lower === 'inbox' => 'inbox',
        str_contains($lower, 'draft') || str_contains($lower, 'brouillon') => 'file-earmark',
        str_contains($lower, 'sent') || str_contains($lower, 'envoy') => 'send',
        str_contains($lower, 'junk') || str_contains($lower, 'spam') || str_contains($lower, 'indésirable') => 'exclamation-octagon',
        str_contains($lower, 'trash') || str_contains($lower, 'corbeille') => 'trash',
        str_contains($lower, 'archive') => 'archive',
        default => 'folder',
    };
}

function sodium_refresh_account_cache(int $accountId, bool $force = false): array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM sodium_mail_accounts WHERE id=?');
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();
    if (!$account) throw new RuntimeException('Compte mail introuvable.');
    if (!$force && !empty($account['last_sync_at']) && strtotime((string) $account['last_sync_at']) > time() - 60) return $account;

    try {
        $stream = sodium_imap_open_account($account);
        $root = sodium_imap_mailbox($account);
        $mailboxes = @imap_getmailboxes($stream, $root, '*') ?: [];
        $folders = [];
        $unreadTotal = 0;
        foreach ($mailboxes as $mailbox) {
            $fullName = imap_utf8((string) $mailbox->name);
            $key = str_starts_with($fullName, $root) ? substr($fullName, strlen($root)) : $fullName;
            $key = $key !== '' ? $key : 'INBOX';
            if(sodium_folder_is_technical($key))continue;
            $status = @imap_status($stream, sodium_imap_mailbox($account, $key), SA_UNSEEN);
            $unread = (int) ($status->unseen ?? 0);
            if (strtoupper($key) === 'INBOX') $unreadTotal = $unread;
            $folders[] = ['key' => $key, 'label' => sodium_folder_display_label($key), 'icon' => sodium_folder_icon($key), 'unread' => $unread];
        }
        if (!$folders) $folders = sodium_account_folders($account);
        $folders=sodium_sort_folders($folders);

        $quotaUsed = $quotaLimit = null;
        $quota = @imap_get_quotaroot($stream, 'INBOX');
        if (is_array($quota)) {
            foreach ($quota as $resource) {
                if (isset($resource['usage'], $resource['limit'])) {
                    $quotaUsed = (int) $resource['usage'];
                    $quotaLimit = (int) $resource['limit'];
                    break;
                }
            }
        }
        imap_close($stream);
        $pdo->prepare('UPDATE sodium_mail_accounts SET folder_cache_json=?, unread_count=?, quota_used_kb=?, quota_limit_kb=?, last_sync_at=NOW(), last_error=NULL WHERE id=?')
            ->execute([json_encode($folders, JSON_UNESCAPED_UNICODE), $unreadTotal, $quotaUsed, $quotaLimit, $accountId]);
    } catch (Throwable $exception) {
        $pdo->prepare('UPDATE sodium_mail_accounts SET last_sync_at=NOW(), last_error=? WHERE id=?')
            ->execute([mb_substr($exception->getMessage(), 0, 1000), $accountId]);
    }

    $stmt->execute([$accountId]);
    return $stmt->fetch() ?: $account;
}

function sodium_format_quota(?int $usedKb, ?int $limitKb): array
{
    if (!$limitKb) return ['label' => 'Quota indisponible', 'percent' => 0];
    $percent = min(100, (int) round(($usedKb ?: 0) * 100 / $limitKb));
    $format = static fn(int $kb): string => number_format($kb / 1024, $kb >= 1024 * 10 ? 0 : 1, ',', ' ') . ' Mo';
    return ['label' => $format((int) $usedKb) . ' / ' . $format($limitKb), 'percent' => $percent];
}

function sodium_convert_to_utf8(string $value, string $charset): string
{
    $charset = trim($charset, " \t\n\r\0\x0B\"'");
    if ($charset === '' || in_array(strtoupper($charset), ['DEFAULT', 'UTF-8', 'US-ASCII', 'ASCII'], true)) return $value;
    static $supported = null;
    $supported ??= array_fill_keys(array_map('strtoupper', mb_list_encodings()), true);
    try {
        if (isset($supported[strtoupper($charset)])) {
            $converted = mb_convert_encoding($value, 'UTF-8', $charset);
            if ($converted !== false) return $converted;
        }
    } catch (Throwable) {
    }
    try {
        $converted = @iconv($charset, 'UTF-8//IGNORE', $value);
        if ($converted !== false) return $converted;
    } catch (Throwable) {
    }
    return $value;
}

function sodium_decode_mime_header(string $value): string
{
    $parts = imap_mime_header_decode($value);
    $decoded = '';
    foreach ($parts as $part) {
        $charset = strtoupper((string) ($part->charset ?? 'DEFAULT'));
        $text = (string) ($part->text ?? '');
        $text = sodium_convert_to_utf8($text, $charset);
        $decoded .= $text;
    }
    return $decoded;
}

function sodium_message_subject(string $value): string
{
    $subject = trim(sodium_decode_mime_header($value));
    return $subject !== '' ? $subject : '(pas d’objet)';
}

function sodium_parse_email_addresses(string $header): array
{
    $addresses = [];
    foreach (imap_rfc822_parse_adrlist($header, '') ?: [] as $address) {
        $mailbox = (string)($address->mailbox ?? '');
        $host = (string)($address->host ?? '');
        $email = strtolower($mailbox . '@' . $host);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $addresses[$email] = [
            'email' => $email,
            'name' => trim(sodium_decode_mime_header((string)($address->personal ?? ''))),
        ];
    }
    return array_values($addresses);
}

function sodium_raw_header_value(string $headers, string $name): string
{
    if ($headers === '' || !preg_match('/^'.preg_quote($name, '/').':\s*(.+(?:\r?\n[ \t].+)*)/mi', $headers, $match)) return '';
    return trim((string)preg_replace('/\r?\n[ \t]+/', ' ', $match[1]));
}

function sodium_imap_search_criteria(string $query, string $scope = 'all', bool $deep = false): string
{
    $query = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $query) ?? ''), 0, 120);
    if ($query === '') return 'ALL';
    if (!in_array($scope, ['correspondents', 'subject', 'body', 'all'], true)) $scope = 'all';
    return 'X-SODIUM-SEARCH:' . base64_encode(json_encode(['query'=>$query, 'scope'=>$scope, 'deep'=>$deep], JSON_UNESCAPED_UNICODE));
}

function sodium_structure_has_attachment($part): bool
{
    $filename = sodium_message_part_filename($part);
    $disposition = strtolower((string)($part->disposition ?? ''));
    $contentId = trim((string)($part->id ?? ''), "<> \t\n\r");
    if ($disposition === 'attachment' || ($filename !== '' && $contentId === '')) return true;
    foreach ($part->parts ?? [] as $child) {
        if (sodium_structure_has_attachment($child)) return true;
    }
    return false;
}

function sodium_fetch_messages(array $account, string $folder, int $limit = 80, int $offset = 0, ?int &$total = null, string $searchCriteria = 'ALL', string $statusFilter = 'all'): array
{
    global $pdo;
    $stream = sodium_imap_open_account($account, $folder);
    $isSentFolder = sodium_folder_icon($folder) === 'send';
    try {
        if (str_starts_with($searchCriteria, 'X-SODIUM-SEARCH:')) {
            $decoded=(string)base64_decode(substr($searchCriteria,16),true);
            $payload=json_decode($decoded,true);
            $query=is_array($payload)?(string)($payload['query']??''):$decoded;
            $scope=is_array($payload)?(string)($payload['scope']??'all'):'all';
            $deep=is_array($payload)&&!empty($payload['deep']);
            $quoted='"'.str_replace(['\\','"'],['\\\\','\\"'],$query).'"';
            $uids=[];
            $seenCriterion = $statusFilter === 'read' ? ' SEEN' : ($statusFilter === 'unread' ? ' UNSEEN' : '');
            $searchFields=match($scope){
                'correspondents'=>['FROM','TO','CC','BCC'],
                'subject'=>['SUBJECT'],
                'body'=>$deep?['BODY','TEXT']:['BODY'],
                default=>$deep?['FROM','TO','CC','BCC','SUBJECT','BODY','TEXT']:['FROM','TO','CC','BCC','SUBJECT','BODY'],
            };
            foreach($searchFields as $field){
                $criteria=$field.' '.$quoted.$seenCriterion;
                $matches=@imap_search($stream,$criteria,SE_UID,'UTF-8');
                if($matches===false)$matches=@imap_search($stream,$criteria,SE_UID);
                if($matches)$uids=array_merge($uids,$matches);
            }
            $uids=array_values(array_unique(array_map('intval',$uids)));
            rsort($uids, SORT_NUMERIC);
        } else {
            if ($statusFilter === 'read') $searchCriteria = $searchCriteria === 'ALL' ? 'SEEN' : $searchCriteria . ' SEEN';
            if ($statusFilter === 'unread') $searchCriteria = $searchCriteria === 'ALL' ? 'UNSEEN' : $searchCriteria . ' UNSEEN';
            $uids = @imap_sort($stream, SORTDATE, true, SE_UID, $searchCriteria) ?: [];
            if (!$uids) {
                $uids = @imap_search($stream, $searchCriteria, SE_UID) ?: [];
                rsort($uids, SORT_NUMERIC);
            }
        }
        $total = count($uids);
        $uids = array_slice($uids, max(0, $offset), max(1, min(100, $limit)));
        if (!$uids) return [];
        $overview = @imap_fetch_overview($stream, implode(',', $uids), FT_UID) ?: [];
        $messages = [];
        foreach ($overview as $item) {
            $from = sodium_decode_mime_header((string) ($item->from ?? 'Expéditeur inconnu'));
            $toRaw = (string)($item->to ?? '');
            $ccRaw = (string)($item->cc ?? '');
            $bccRaw = (string)($item->bcc ?? '');
            if ($isSentFolder && ($toRaw === '' || $bccRaw === '')) {
                $rawHeaders = (string)(@imap_fetchheader($stream, (int)($item->uid ?? 0), FT_UID) ?: '');
                if ($toRaw === '') $toRaw = sodium_raw_header_value($rawHeaders, 'To');
                if ($ccRaw === '') $ccRaw = sodium_raw_header_value($rawHeaders, 'Cc');
                if ($bccRaw === '') $bccRaw = sodium_raw_header_value($rawHeaders, 'Bcc');
            }
            $subject = sodium_message_subject((string) ($item->subject ?? ''));
            $messageId = trim((string) ($item->message_id ?? ''));
            $messageKey = hash('sha256', $messageId !== '' ? strtolower($messageId) : ((int)$account['id'] . '|' . $folder . '|' . (int)($item->uid ?? 0)));
            $message = [
                'uid' => (int) ($item->uid ?? 0),
                'message_id' => $messageId,
                'message_key' => $messageKey,
                'from' => $from,
                'from_raw' => (string) ($item->from ?? ''),
                'to_raw' => $toRaw,
                'cc_raw' => $ccRaw,
                'bcc_raw' => $bccRaw,
                'to_addresses' => sodium_parse_email_addresses($toRaw),
                'cc_addresses' => sodium_parse_email_addresses($ccRaw),
                'bcc_addresses' => sodium_parse_email_addresses($bccRaw),
                'subject' => $subject,
                'date' => !empty($item->date) ? date('d/m/Y H:i', strtotime((string) $item->date)) : '',
                'timestamp' => !empty($item->date) ? (int) strtotime((string) $item->date) : 0,
                'unread' => empty($item->seen),
                'flagged' => !empty($item->flagged),
                'has_attachment' => false,
            ];
            $messages[] = $message;
            if (!in_array(sodium_folder_icon($folder), ['exclamation-octagon', 'trash'], true)) {
                sodium_index_message_contacts((int) $account['id'], [$message['from_raw'], $message['to_raw'], $message['cc_raw'], $message['bcc_raw']]);
            }
        }
        if ($messages) {
            $messageKeys = array_column($messages, 'message_key');
            $cacheStmt = $pdo->prepare('SELECT message_key,has_attachment FROM sodium_message_list_cache
                WHERE mail_account_id=? AND message_key IN ('.implode(',', array_fill(0, count($messageKeys), '?')).')');
            $cacheStmt->execute(array_merge([(int)$account['id']], $messageKeys));
            $attachmentCache = [];
            foreach ($cacheStmt->fetchAll() as $cached) $attachmentCache[(string)$cached['message_key']] = !empty($cached['has_attachment']);
            $saveCache = $pdo->prepare('INSERT INTO sodium_message_list_cache (mail_account_id,message_key,has_attachment)
                VALUES (?,?,?) ON DUPLICATE KEY UPDATE has_attachment=VALUES(has_attachment),updated_at=NOW()');
            foreach ($messages as &$message) {
                if (array_key_exists($message['message_key'], $attachmentCache)) {
                    $message['has_attachment'] = $attachmentCache[$message['message_key']];
                    continue;
                }
                $structure = @imap_fetchstructure($stream, (int)$message['uid'], FT_UID);
                $message['has_attachment'] = $structure ? sodium_structure_has_attachment($structure) : false;
                $saveCache->execute([(int)$account['id'], $message['message_key'], $message['has_attachment'] ? 1 : 0]);
            }
            unset($message);
        }
        usort($messages, static fn(array $left, array $right): int =>
            ($right['timestamp'] <=> $left['timestamp']) ?: ($right['uid'] <=> $left['uid'])
        );
        return $messages;
    } finally {
        imap_close($stream);
    }
}

function sodium_index_message_contacts(int $accountId, array $addressHeaders): void
{
    global $pdo;
    foreach ($addressHeaders as $header) {
        if (trim((string) $header) === '') continue;
        foreach (imap_rfc822_parse_adrlist((string) $header, '') ?: [] as $address) {
            $email = strtolower(trim((string) ($address->mailbox ?? '') . '@' . (string) ($address->host ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $name = sodium_decode_mime_header((string) ($address->personal ?? ''));
            $pdo->prepare("INSERT INTO sodium_contacts (mail_account_id,email,display_name,last_seen_at)
                VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE display_name=IF(VALUES(display_name)<>'',VALUES(display_name),display_name),last_seen_at=NOW()")
                ->execute([$accountId, $email, mb_substr($name, 0, 190)]);
        }
    }
}

function sodium_process_auto_replies(array $account, int $limit = 10): int
{
    global $pdo;
    $stmt=$pdo->prepare("SELECT r.*,COALESCE(s.last_uid,0) last_uid,s.mail_account_id state_account_id FROM sodium_auto_reply_rules r
        LEFT JOIN sodium_auto_reply_rule_accounts s ON s.rule_id=r.id AND s.mail_account_id=?
        WHERE r.enabled=1 AND (r.applies_all=1 OR s.mail_account_id IS NOT NULL)
        AND (r.starts_at IS NULL OR r.starts_at<=NOW()) AND (r.ends_at IS NULL OR r.ends_at>=NOW()) ORDER BY r.id");
    $stmt->execute([(int)$account['id']]);$rules=$stmt->fetchAll();
    if(!$rules)return 0;
    $stream=sodium_imap_open_account($account,'INBOX');$sent=0;
    try{
        foreach($rules as $rule){
            if(trim((string)$rule['content_html'])==='')continue;
            $lastUid=(int)$rule['last_uid'];
            if($rule['state_account_id']===null){
                $baseline=@imap_search($stream,'ALL',SE_UID)?:[];$lastUid=$baseline?max(array_map('intval',$baseline)):0;
                $pdo->prepare('INSERT INTO sodium_auto_reply_rule_accounts(rule_id,mail_account_id,last_uid) VALUES(?,?,?)')->execute([(int)$rule['id'],(int)$account['id'],$lastUid]);
                continue;
            }
            $uids=@imap_search($stream,'UID '.($lastUid+1).':*',SE_UID)?:[];
            sort($uids,SORT_NUMERIC);$uids=array_slice($uids,0,max(1,min(50,$limit)));
            foreach($uids as $uid){
                $uid=(int)$uid;$header=(string)@imap_fetchheader($stream,$uid,FT_UID);
                if(preg_match('/^(Auto-Submitted:\\s*(?!no)|Precedence:\\s*(bulk|list|junk)|X-Auto-Response-Suppress:)/mi',$header)){$lastUid=max($lastUid,$uid);continue;}
                $overview=(@imap_fetch_overview($stream,(string)$uid,FT_UID)?:[])[0]??null;
                if(!$overview){$lastUid=max($lastUid,$uid);continue;}
                $addresses=imap_rfc822_parse_adrlist((string)($overview->from??''),'')?:[];
                $recipient=$addresses?strtolower((string)$addresses[0]->mailbox.'@'.(string)$addresses[0]->host):'';
                if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||preg_match('/(no-?reply|mailer-daemon|postmaster)/i',$recipient)){$lastUid=max($lastUid,$uid);continue;}
                sodium_send_smtp_message($account,[$recipient],[],[],trim((string)$rule['subject'])?:'Réponse automatique','<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.55">'.sodium_sanitize_email_html((string)$rule['content_html']).'</div>',[],'normal',(string)($account['display_name']?:$account['email_address']),trim((string)($overview->message_id??'')));
                $sent++;$lastUid=max($lastUid,$uid);
            }
            $pdo->prepare("INSERT INTO sodium_auto_reply_rule_accounts(rule_id,mail_account_id,last_uid) VALUES(?,?,?)
                ON DUPLICATE KEY UPDATE last_uid=GREATEST(last_uid,VALUES(last_uid))")->execute([(int)$rule['id'],(int)$account['id'],$lastUid]);
        }
    }finally{imap_close($stream);}
    return $sent;
}

function sodium_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function sodium_vapid_details(): array
{
    static $details=null;
    if($details!==null)return $details;
    $pem=(string)file_get_contents(dirname(__DIR__,3).'/.sodium-vapid-private.pem');
    $key=openssl_pkey_get_private($pem);$info=$key?openssl_pkey_get_details($key):false;
    if(!$key||!is_array($info)||empty($info['ec']['x'])||empty($info['ec']['y']))throw new RuntimeException('Clé Web Push indisponible.');
    return $details=['key'=>$key,'public'=>sodium_base64url("\x04".$info['ec']['x'].$info['ec']['y'])];
}

function sodium_ecdsa_der_to_raw(string $der, int $size=32): string
{
    $offset=2;if((ord($der[1])&0x80)!==0)$offset=2+(ord($der[1])&0x7f);
    if(ord($der[$offset]??"\0")!==2)throw new RuntimeException('Signature VAPID invalide.');
    $rLength=ord($der[++$offset]);$r=substr($der,++$offset,$rLength);$offset+=$rLength;
    if(ord($der[$offset]??"\0")!==2)throw new RuntimeException('Signature VAPID invalide.');
    $sLength=ord($der[++$offset]);$s=substr($der,++$offset,$sLength);
    return str_pad(ltrim($r,"\0"),$size,"\0",STR_PAD_LEFT).str_pad(ltrim($s,"\0"),$size,"\0",STR_PAD_LEFT);
}

function sodium_send_web_push_to_user(int $userId): int
{
    global $pdo;
    $details=sodium_vapid_details();
    $stmt=$pdo->prepare('SELECT id,endpoint FROM sodium_push_subscriptions WHERE user_id=? AND enabled=1');
    $stmt->execute([$userId]);$sent=0;
    foreach($stmt->fetchAll() as $subscription){
        $endpoint=(string)$subscription['endpoint'];$parts=parse_url($endpoint);
        if(empty($parts['scheme'])||empty($parts['host'])||$parts['scheme']!=='https')continue;
        $audience='https://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
        $header=sodium_base64url(json_encode(['typ'=>'JWT','alg'=>'ES256']));
        $claims=sodium_base64url(json_encode(['aud'=>$audience,'exp'=>time()+43200,'sub'=>'mailto:'.sodium_system_sender_email()]));
        openssl_sign($header.'.'.$claims,$signature,$details['key'],OPENSSL_ALGO_SHA256);
        $jwt=$header.'.'.$claims.'.'.sodium_base64url(sodium_ecdsa_der_to_raw($signature));
        $curl=curl_init($endpoint);
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,
            CURLOPT_HTTPHEADER=>['TTL: 60','Content-Length: 0','Authorization: vapid t='.$jwt.', k='.$details['public']]]);
        curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if(in_array($status,[201,202],true))$sent++;
        elseif(in_array($status,[404,410],true))$pdo->prepare('DELETE FROM sodium_push_subscriptions WHERE id=?')->execute([(int)$subscription['id']]);
    }
    return $sent;
}

function sodium_refresh_accounts_and_push(): array
{
    global $pdo;
    $result=['accounts'=>0,'new_messages'=>0,'pushes'=>0];
    if(!(int)$pdo->query("SELECT GET_LOCK('sodium_background_refresh',0)")->fetchColumn())return $result;
    try{
        $accounts=$pdo->query("SELECT * FROM sodium_mail_accounts WHERE account_status='active' AND password_cipher IS NOT NULL AND password_cipher<>''")->fetchAll();
        foreach($accounts as $account){
            $before=(int)$account['unread_count'];
            try{
                $fresh=sodium_refresh_account_cache((int)$account['id'],true);
                sodium_process_auto_replies($fresh);
                $after=(int)$fresh['unread_count'];$result['accounts']++;
                if($after>$before){
                    $result['new_messages']+=($after-$before);
                    $users=$pdo->prepare('SELECT DISTINCT user_id FROM sodium_user_mail_accounts WHERE mail_account_id=? AND can_read=1');
                    $users->execute([(int)$account['id']]);
                    foreach($users->fetchAll(PDO::FETCH_COLUMN) as $userId)$result['pushes']+=sodium_send_web_push_to_user((int)$userId);
                }
            }catch(Throwable $exception){error_log('[Sodium background refresh] account='.(int)$account['id'].' '.$exception->getMessage());}
        }
    }finally{$pdo->query("SELECT RELEASE_LOCK('sodium_background_refresh')");}
    return $result;
}

function sodium_index_account_history(array $account): int
{
    global $pdo;
    $indexed = 0;
    foreach (sodium_account_folders($account) as $folder) {
        $folderKey = (string) ($folder['key'] ?? '');
        if ($folderKey === '' || in_array(sodium_folder_icon($folderKey), ['exclamation-octagon', 'trash'], true)) continue;
        try {
            $state = $pdo->prepare('SELECT last_uid FROM sodium_contact_index_state WHERE mail_account_id=? AND folder_key=?');
            $state->execute([(int) $account['id'], $folderKey]);
            $lastUid = (int) ($state->fetchColumn() ?: 0);
            $stream = sodium_imap_open_account($account, $folderKey);
            try {
                $criteria = $lastUid > 0 ? 'UID ' . ($lastUid + 1) . ':*' : 'ALL';
                $uids = @imap_search($stream, $criteria, SE_UID) ?: [];
                $uids = array_values(array_filter(array_map('intval', $uids), static fn(int $uid): bool => $uid > $lastUid));
                sort($uids, SORT_NUMERIC);
                foreach (array_chunk($uids, 250) as $chunk) {
                    $overview = @imap_fetch_overview($stream, implode(',', $chunk), FT_UID) ?: [];
                    foreach ($overview as $item) {
                        sodium_index_message_contacts((int) $account['id'], [
                            (string) ($item->from ?? ''),
                            (string) ($item->to ?? ''),
                            (string) ($item->cc ?? ''),
                        ]);
                        $indexed++;
                    }
                }
                $maxUid = $uids ? max($uids) : $lastUid;
                $save = $pdo->prepare("INSERT INTO sodium_contact_index_state (mail_account_id,folder_key,last_uid,indexed_at)
                    VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE last_uid=GREATEST(last_uid,VALUES(last_uid)),indexed_at=NOW()");
                $save->execute([(int) $account['id'], $folderKey, $maxUid]);
            } finally {
                imap_close($stream);
            }
        } catch (Throwable) {
            continue;
        }
    }
    return $indexed;
}

function sodium_message_metadata(array $messages): array
{
    global $pdo;
    if (!$messages) return [];
    $keysByAccount = [];
    foreach ($messages as $message) $keysByAccount[(int)$message['account']['id']][] = (string)$message['message_key'];
    $metadata = [];
    foreach ($keysByAccount as $accountId => $keys) {
        $keys = array_values(array_unique($keys));
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $tagVisibility=sodium_can_manage_all('sodium_labels')?'1=1':'(t.created_by=? OR t.is_shared=1)';
        $stmt = $pdo->prepare("SELECT mt.message_key,t.id,t.name,t.color FROM sodium_message_tags mt INNER JOIN sodium_tags t ON t.id=mt.tag_id WHERE mt.mail_account_id=? AND mt.message_key IN ($placeholders) AND $tagVisibility");
        $tagParams=array_merge([$accountId],$keys);
        if(!sodium_can_manage_all('sodium_labels'))$tagParams[]=(int)(current_user()['id']??0);
        $stmt->execute($tagParams);
        foreach ($stmt->fetchAll() as $row) $metadata[$accountId][$row['message_key']]['tags'][] = $row;
        $stmt = $pdo->prepare("SELECT r.source_message_key,r.replied_at,u.first_name,u.last_name,u.username FROM sodium_message_replies r INNER JOIN users u ON u.id=r.user_id WHERE r.mail_account_id=? AND r.source_message_key IN ($placeholders) ORDER BY r.replied_at DESC");
        $stmt->execute(array_merge([$accountId], $keys));
        foreach ($stmt->fetchAll() as $row) {
            $name = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) ?: $row['username'];
            $metadata[$accountId][$row['source_message_key']]['replies'][] = ['name'=>$name,'date'=>$row['replied_at']];
        }
    }
    return $metadata;
}

function sodium_message_part_data($stream, int $uid, string $partNumber, int $encoding): string
{
    $data = $partNumber === '' ? (string) @imap_body($stream, $uid, FT_UID | FT_PEEK) : (string) @imap_fetchbody($stream, $uid, $partNumber, FT_UID | FT_PEEK);
    return match ($encoding) {
        ENCBASE64 => (string) base64_decode($data, true),
        ENCQUOTEDPRINTABLE => quoted_printable_decode($data),
        default => $data,
    };
}

function sodium_message_part_charset($part): string
{
    $parameters = $part->parameters ?? [];
    $dispositionParameters = $part->dparameters ?? [];
    if (is_object($parameters)) $parameters = [$parameters];
    if (is_object($dispositionParameters)) $dispositionParameters = [$dispositionParameters];
    if (!is_array($parameters)) $parameters = [];
    if (!is_array($dispositionParameters)) $dispositionParameters = [];
    foreach (array_merge($parameters, $dispositionParameters) as $parameter) {
        if (strtolower((string)($parameter->attribute ?? '')) === 'charset') return (string)$parameter->value;
    }
    return 'UTF-8';
}

function sodium_message_part_filename($part): string
{
    $parameters = $part->parameters ?? [];
    $dispositionParameters = $part->dparameters ?? [];
    if (is_object($parameters)) $parameters = [$parameters];
    if (is_object($dispositionParameters)) $dispositionParameters = [$dispositionParameters];
    if (!is_array($parameters)) $parameters = [];
    if (!is_array($dispositionParameters)) $dispositionParameters = [];
    foreach (array_merge($dispositionParameters, $parameters) as $parameter) {
        if (in_array(strtolower((string)($parameter->attribute ?? '')), ['filename','name'], true)) return sodium_decode_mime_header((string)$parameter->value);
    }
    return '';
}

function sodium_attachment_preview_mime(string $name, string $reportedMime): ?string
{
    $mime = strtolower(trim(explode(';', $reportedMime, 2)[0]));
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = [
        'application/pdf'=>'application/pdf',
        'application/x-pdf'=>'application/pdf',
        'image/png'=>'image/png',
        'image/jpeg'=>'image/jpeg',
        'image/jpg'=>'image/jpeg',
        'image/gif'=>'image/gif',
        'image/webp'=>'image/webp',
        'image/bmp'=>'image/bmp',
        'image/avif'=>'image/avif',
    ];
    if (isset($allowed[$mime])) return $allowed[$mime];
    return [
        'pdf'=>'application/pdf',
        'png'=>'image/png',
        'jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg',
        'gif'=>'image/gif',
        'webp'=>'image/webp',
        'bmp'=>'image/bmp',
        'avif'=>'image/avif',
    ][$extension] ?? null;
}

function sodium_walk_message_parts($stream, int $uid, $part, string $number, array &$result): void
{
    if (!empty($part->parts)) {
        foreach ($part->parts as $index => $child) sodium_walk_message_parts($stream, $uid, $child, $number === '' ? (string)($index+1) : $number.'.'.($index+1), $result);
        return;
    }
    $filename = sodium_message_part_filename($part);
    $disposition = strtolower((string)($part->disposition ?? ''));
    if ($filename !== '' || $disposition === 'attachment') {
        $contentId = trim((string)($part->id ?? ''), "<> \t\n\r");
        $type = (int)($part->type ?? TYPEAPPLICATION);
        $subtype = strtolower((string)($part->subtype ?? 'octet-stream'));
        if ($subtype === 'jpg') $subtype = 'jpeg';
        $mime = match ($type) {
            TYPEIMAGE => 'image/'.$subtype,
            TYPETEXT => 'text/'.$subtype,
            TYPEAUDIO => 'audio/'.$subtype,
            TYPEVIDEO => 'video/'.$subtype,
            TYPEAPPLICATION => 'application/'.$subtype,
            default => 'application/octet-stream',
        };
        $result['attachments'][] = ['part'=>$number, 'name'=>$filename ?: 'Pièce jointe', 'size'=>(int)($part->bytes ?? 0), 'mime'=>$mime, 'content_id'=>$contentId];
        return;
    }
    if ((int)($part->type ?? -1) === TYPETEXT && in_array(strtoupper((string)($part->subtype ?? '')), ['PLAIN','HTML'], true)) {
        $data = sodium_message_part_data($stream, $uid, $number, (int)($part->encoding ?? 0));
        $charset = sodium_message_part_charset($part);
        $data = sodium_convert_to_utf8($data, $charset);
        if (strtoupper((string)$part->subtype) === 'HTML') $result['html'] .= $data; else $result['plain'] .= $data;
    }
}

function sodium_fetch_message_content(array $account, string $folder, int $uid): array
{
    $stream = sodium_imap_open_account($account, $folder);
    try {
        $overview = (@imap_fetch_overview($stream, (string)$uid, FT_UID) ?: [])[0] ?? null;
        if (!$overview) throw new RuntimeException('Message introuvable.');
        $structure = @imap_fetchstructure($stream, $uid, FT_UID);
        $result = ['html'=>'','plain'=>'','attachments'=>[]];
        if ($structure) sodium_walk_message_parts($stream, $uid, $structure, '', $result);
        if ($result['html'] === '' && $result['plain'] === '' && $structure) {
            $result['plain'] = sodium_message_part_data($stream, $uid, '', (int)($structure->encoding ?? 0));
        }
        $fromRaw = (string)($overview->from ?? '');
        $fromAddresses = sodium_parse_email_addresses($fromRaw);
        $senderEmail = (string)($fromAddresses[0]['email'] ?? '');
        $rawHeaders = (string)(@imap_fetchheader($stream, $uid, FT_UID) ?: '');
        $parsedHeaders = $rawHeaders !== '' ? @imap_rfc822_parse_headers($rawHeaders) : false;
        $replyToRaw = is_object($parsedHeaders) ? (string)($parsedHeaders->reply_toaddress ?? '') : '';
        $replyToAddresses = $replyToRaw !== '' ? sodium_parse_email_addresses($replyToRaw) : [];
        $replyEmail = (string)($replyToAddresses[0]['email'] ?? $senderEmail);
        $replyLabel = $replyToRaw !== '' ? sodium_decode_mime_header($replyToRaw) : sodium_decode_mime_header($fromRaw);
        $messageId = trim((string)($overview->message_id ?? ''));
        $toRaw = (string)($overview->to ?? '');
        $ccRaw = (string)($overview->cc ?? '');
        return [
            'uid'=>$uid,
            'message_id'=>$messageId,
            'message_key'=>hash('sha256', $messageId !== '' ? strtolower($messageId) : ((int)$account['id'].'|'.$folder.'|'.$uid)),
            'from'=>sodium_decode_mime_header($fromRaw),
            'sender_email'=>filter_var($senderEmail, FILTER_VALIDATE_EMAIL) ? $senderEmail : '',
            'reply_email'=>filter_var($replyEmail, FILTER_VALIDATE_EMAIL) ? $replyEmail : '',
            'reply_to'=>$replyLabel,
            'reply_to_addresses'=>$replyToAddresses,
            'to'=>sodium_decode_mime_header($toRaw),
            'cc'=>sodium_decode_mime_header($ccRaw),
            'to_addresses'=>sodium_parse_email_addresses($toRaw),
            'cc_addresses'=>sodium_parse_email_addresses($ccRaw),
            'subject'=>sodium_message_subject((string)($overview->subject ?? '')),
            'date'=>!empty($overview->date) ? date('d/m/Y H:i', strtotime((string)$overview->date)) : '',
            'html'=>$result['html'] !== '' ? sodium_sanitize_email_html($result['html'], true) : nl2br(e($result['plain'])),
            'attachments'=>$result['attachments'],
        ];
    } finally {
        imap_close($stream);
    }
}

function sodium_human_date(int $timestamp): string
{
    if (!$timestamp) return '';
    if ($timestamp > time() + 30) {
        $future = $timestamp - time();
        return match (true) {
            $future < 3600 => 'dans ' . max(1, (int) ceil($future / 60)) . ' min',
            $future < 86400 => 'dans ' . max(1, (int) ceil($future / 3600)) . ' h',
            default => 'dans ' . max(1, (int) ceil($future / 86400)) . ' j',
        };
    }
    $diff = max(0, time() - $timestamp);
    return match (true) {
        $diff < 60 => 'à l’instant',
        $diff < 3600 => 'il y a ' . max(1, (int) floor($diff / 60)) . ' min',
        $diff < 86400 => 'il y a ' . (int) floor($diff / 3600) . ' h',
        $diff < 172800 => 'hier',
        $diff < 604800 => 'il y a ' . (int) floor($diff / 86400) . ' jours',
        default => date('d/m/Y', $timestamp),
    };
}

function sodium_system_folder(array $account, string $type): string
{
    $folders = sodium_account_folders($account);
    $wantedIcon = ['archive'=>'archive', 'junk'=>'exclamation-octagon', 'trash'=>'trash', 'drafts'=>'file-earmark', 'sent'=>'send'][$type] ?? 'folder';
    if ($type === 'sent') {
        foreach ($folders as $folder) if (strcasecmp((string)$folder['key'], 'INBOX.Sent') === 0) return (string)$folder['key'];
        foreach ($folders as $folder) if (strcasecmp(basename(str_replace('.', '/', (string)$folder['key'])), 'Sent') === 0) return (string)$folder['key'];
        $sentFolders=array_values(array_filter($folders,static fn(array $folder):bool=>($folder['icon']??'')==='send'));
        if($sentFolders){usort($sentFolders,static fn(array $a,array $b):int=>strlen((string)$a['key'])<=>strlen((string)$b['key']));return (string)$sentFolders[0]['key'];}
    }
    foreach ($folders as $folder) {
        if (($folder['icon'] ?? '') === $wantedIcon) return (string) $folder['key'];
    }
    return ['archive'=>'Archive', 'junk'=>'Junk', 'trash'=>'Trash', 'drafts'=>'Drafts', 'sent'=>'Sent'][$type] ?? 'INBOX';
}

function sodium_bulk_move(array $account, string $sourceFolder, array $uids, string $targetFolder): void
{
    if (!$uids) return;
    $stream = sodium_imap_open_account($account, $sourceFolder, false);
    try {
        if (!@imap_mail_move($stream, implode(',', array_map('intval', $uids)), $targetFolder, CP_UID)) {
            $errors = imap_errors() ?: [];
            throw new RuntimeException($errors ? end($errors) : 'Déplacement IMAP impossible.');
        }
        @imap_expunge($stream);
    } finally {
        imap_close($stream);
    }
}

function sodium_set_seen(array $account, string $folder, array $uids, bool $seen): void
{
    if (!$uids) return;
    $stream = sodium_imap_open_account($account, $folder, false);
    try {
        $sequence = implode(',', array_map('intval', $uids));
        if ($seen) @imap_setflag_full($stream, $sequence, '\\Seen', ST_UID);
        else @imap_clearflag_full($stream, $sequence, '\\Seen', ST_UID);
    } finally {
        imap_close($stream);
    }
}

function sodium_set_flagged(array $account, string $folder, array $uids, bool $flagged): void
{
    if (!$uids) return;
    $stream = sodium_imap_open_account($account, $folder, false);
    try {
        $sequence = implode(',', array_map('intval', $uids));
        if ($flagged) @imap_setflag_full($stream, $sequence, '\\Flagged', ST_UID);
        else @imap_clearflag_full($stream, $sequence, '\\Flagged', ST_UID);
    } finally {
        imap_close($stream);
    }
}

function sodium_message_part_by_number($structure, string $number)
{
    if ($number === '') return $structure;
    $part = $structure;
    foreach (array_map('intval', explode('.', $number)) as $index) {
        if ($index < 1 || empty($part->parts[$index - 1])) return null;
        $part = $part->parts[$index - 1];
    }
    return $part;
}

function sodium_fetch_attachment(array $account, string $folder, int $uid, string $partNumber): array
{
    $stream = sodium_imap_open_account($account, $folder);
    try {
        $structure = @imap_fetchstructure($stream, $uid, FT_UID);
        $part = $structure ? sodium_message_part_by_number($structure, $partNumber) : null;
        if (!$part) throw new RuntimeException('Pièce jointe introuvable.');
        $filename = sodium_message_part_filename($part) ?: 'piece-jointe';
        $data = sodium_message_part_data($stream, $uid, $partNumber, (int)($part->encoding ?? 0));
        return ['name'=>$filename,'data'=>$data,'type'=>(int)($part->type ?? TYPEAPPLICATION),'subtype'=>strtolower((string)($part->subtype ?? 'octet-stream'))];
    } finally {
        imap_close($stream);
    }
}

function sodium_send_smtp_message(array $account, array $to, array $cc, array $bcc, string $subject, string $html, array $attachments = [], string $priority = 'normal', string $senderName = '', string $inReplyTo = ''): void
{
    $host = trim((string) $account['smtp_host']);
    $port = (int) $account['smtp_port'];
    $encryption = strtolower((string) $account['smtp_encryption']);
    $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $isLocalHost = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    $context = stream_context_create(['ssl' => [
        'verify_peer' => !$isLocalHost,
        'verify_peer_name' => !$isLocalHost,
        'allow_self_signed' => $isLocalHost,
    ]]);
    $socket = @stream_socket_client($target . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $reason = trim((string) $errstr) !== '' ? trim((string) $errstr) : 'aucune réponse du serveur';
        throw new RuntimeException('Connexion impossible vers ' . $host . ':' . $port . ' (' . strtoupper($encryption ?: 'sans chiffrement') . ', code ' . (int) $errno . ') : ' . $reason);
    }
    stream_set_timeout($socket, 20);
    try {
        mail_smtp_read($socket);
        mail_smtp_cmd($socket, 'EHLO mail.jessysystem.ovh', [250]);
        if ($encryption === 'tls') {
            mail_smtp_cmd($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Activation TLS impossible.');
            mail_smtp_cmd($socket, 'EHLO mail.jessysystem.ovh', [250]);
        }
        $password = sodium_decrypt_secret($account['password_cipher'] ?? '');
        if ($password === '') throw new RuntimeException('Mot de passe SMTP manquant.');
        mail_smtp_cmd($socket, 'AUTH LOGIN', [334]);
        mail_smtp_cmd($socket, base64_encode((string) ($account['login_name'] ?: $account['email_address'])), [334]);
        mail_smtp_cmd($socket, base64_encode($password), [235]);
        mail_smtp_cmd($socket, 'MAIL FROM:<' . $account['email_address'] . '>', [250]);
        foreach (array_unique(array_merge($to, $cc, $bcc)) as $recipient) {
            mail_smtp_cmd($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        }
        mail_smtp_cmd($socket, 'DATA', [354]);
        $fromName = str_replace(["\r", "\n"], '', trim($senderName !== '' ? $senderName : (string) ($account['display_name'] ?: $account['email_address'])));
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $senderDomain = strtolower((string) substr(strrchr((string) $account['email_address'], '@') ?: '@localhost', 1));
        if (!preg_match('/^[a-z0-9.-]+$/', $senderDomain)) $senderDomain = 'localhost';
        $headers = [
            'From: ' . mb_encode_mimeheader($fromName) . ' <' . $account['email_address'] . '>',
            'Reply-To: ' . mb_encode_mimeheader($fromName) . ' <' . $account['email_address'] . '>',
            'To: ' . implode(', ', array_map(static fn(string $mail): string => '<' . $mail . '>', $to)),
            'Subject: ' . mb_encode_mimeheader($subject),
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $senderDomain . '>',
            'MIME-Version: 1.0',
            'X-Mailer: Sodium Webmail',
        ];
        $inReplyTo = trim(str_replace(["\r","\n"], '', $inReplyTo));
        if ($inReplyTo !== '' && preg_match('/^<[^<>\\s]+>$/', $inReplyTo)) {
            $headers[] = 'In-Reply-To: ' . $inReplyTo;
            $headers[] = 'References: ' . $inReplyTo;
        }
        if ($cc) $headers[] = 'Cc: ' . implode(', ', array_map(static fn(string $mail): string => '<' . $mail . '>', $cc));
        if ($priority === 'high') { $headers[]='X-Priority: 1'; $headers[]='Importance: high'; }
        if ($priority === 'low') { $headers[]='X-Priority: 5'; $headers[]='Importance: low'; }
        $plainText = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)>/i', "\n", $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainEncoded=rtrim(chunk_split(base64_encode($plainText),76,"\r\n"));
        $htmlEncoded=rtrim(chunk_split(base64_encode($html),76,"\r\n"));
        $alternativeBoundary = 'sodium_alt_' . bin2hex(random_bytes(12));
        $alternativeBody = '--' . $alternativeBoundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $plainEncoded . "\r\n"
            . '--' . $alternativeBoundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $htmlEncoded . "\r\n"
            . '--' . $alternativeBoundary . "--\r\n";
        if ($attachments) {
            $boundary='sodium_'.bin2hex(random_bytes(16));
            $headers[]='Content-Type: multipart/mixed; boundary="'.$boundary.'"';
            $body='--'.$boundary."\r\nContent-Type: multipart/alternative; boundary=\"".$alternativeBoundary."\"\r\n\r\n".$alternativeBody;
            foreach($attachments as $attachment){
                $filename=str_replace(["\r","\n",'"'],'',(string)$attachment['name']);
                $body.='--'.$boundary."\r\nContent-Type: ".($attachment['type']?:'application/octet-stream').'; name="'.$filename."\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n".chunk_split(base64_encode($attachment['data']))."\r\n";
            }
            $body.='--'.$boundary."--\r\n";
            $message=implode("\r\n",$headers)."\r\n\r\n".$body;
        } else {
            $headers[]='Content-Type: multipart/alternative; boundary="'.$alternativeBoundary.'"';
            $message=implode("\r\n",$headers)."\r\n\r\n".$alternativeBody;
        }
        mail_smtp_cmd($socket, mail_smtp_data_escape($message) . "\r\n.", [250]);
        mail_smtp_cmd($socket, 'QUIT', [221]);
        try {
            $sentFolder=sodium_system_folder($account,'sent');
            $imap=sodium_imap_open_account($account,$sentFolder);
            try {
                if(!@imap_append($imap,sodium_imap_mailbox($account,$sentFolder),$message,"\\Seen")) {
                    throw new RuntimeException('Copie IMAP refusée.');
                }
            } finally { imap_close($imap); }
        } catch(Throwable $appendException) {
            error_log('[Sodium sent copy] account='.(int)($account['id']??0).' '.$appendException->getMessage());
        }
    } finally {
        fclose($socket);
    }
}

function sodium_process_scheduled_messages(int $limit = 20): array
{
    global $pdo;
    sodium_ensure_schema();
    $result = ['sent'=>0,'failed'=>0];
    $limit = max(1,min(100,$limit));
    $stmt=$pdo->query("SELECT m.*,a.*,m.id composed_id,m.user_id composed_user_id,m.content_html composed_content_html
        FROM sodium_composed_messages m
        INNER JOIN sodium_mail_accounts a ON a.id=m.mail_account_id
        WHERE m.status='scheduled' AND m.scheduled_at<=NOW() AND a.account_status='active'
        ORDER BY m.scheduled_at,m.id LIMIT ".$limit);
    foreach($stmt->fetchAll() as $message){
        $id=(int)$message['composed_id'];
        try{
            $to=json_decode((string)$message['to_json'],true)?:[];
            $cc=json_decode((string)$message['cc_json'],true)?:[];
            $bcc=json_decode((string)$message['bcc_json'],true)?:[];
            $stored=json_decode((string)($message['attachments_json']??''),true)?:[];
            $attachments=[];
            foreach($stored as $file){
                $attachments[]=['name'=>(string)($file['name']??'piece-jointe'),'type'=>(string)($file['type']??'application/octet-stream'),'data'=>(string)base64_decode((string)($file['data']??''),true)];
            }
            $userStmt=$pdo->prepare('SELECT first_name,last_name,username FROM users WHERE id=?');
            $userStmt->execute([(int)$message['composed_user_id']]);$sender=$userStmt->fetch()?:[];
            $senderName=trim((string)($sender['first_name']??'').' '.(string)($sender['last_name']??''))?:((string)($sender['username']??''));
            if(!empty($message['signature_id'])){
                $signatureStmt=$pdo->prepare('SELECT sender_name FROM sodium_signatures WHERE id=? AND mail_account_id=?');
                $signatureStmt->execute([(int)$message['signature_id'],(int)$message['mail_account_id']]);
                $signatureName=trim((string)($signatureStmt->fetchColumn()?:''));
                if($signatureName!=='')$senderName=$signatureName;
            }
            $body='<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.55;color:#1f2937">'.sodium_sanitize_email_html((string)$message['composed_content_html']).'</div>';
            sodium_send_smtp_message($message,$to,$cc,$bcc,(string)$message['subject'],$body,$attachments,(string)$message['priority'],$senderName,(string)($message['in_reply_to']??''));
            $pdo->prepare("UPDATE sodium_composed_messages SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=? AND status='scheduled'")->execute([$id]);
            if((int)($message['reply_account_id']??0)===(int)$message['mail_account_id']&&preg_match('/^[a-f0-9]{64}$/',(string)($message['reply_message_key']??''))){
                $pdo->prepare('INSERT INTO sodium_message_replies (mail_account_id,source_message_key,user_id,subject,content_html,replied_at) VALUES (?,?,?,?,?,NOW())')
                    ->execute([(int)$message['mail_account_id'],(string)$message['reply_message_key'],(int)$message['composed_user_id'],(string)$message['subject'],$body]);
            }
            $result['sent']++;
        }catch(Throwable $exception){
            $pdo->prepare("UPDATE sodium_composed_messages SET status='failed',last_error=? WHERE id=?")->execute([mb_substr($exception->getMessage(),0,1000),$id]);
            error_log('[Sodium scheduled SMTP] message='.$id.' error='.$exception->getMessage());
            $result['failed']++;
        }
    }
    return $result;
}

function sodium_restore_composed_sent_copy(int $composedId): bool
{
    global $pdo;
    sodium_ensure_schema();
    $stmt=$pdo->prepare("SELECT m.*,a.*,m.id composed_id,m.subject composed_subject,m.content_html composed_content,
        m.created_at composed_created_at,m.sent_at composed_sent_at FROM sodium_composed_messages m
        INNER JOIN sodium_mail_accounts a ON a.id=m.mail_account_id WHERE m.id=? AND m.status='sent'");
    $stmt->execute([$composedId]);$message=$stmt->fetch();if(!$message)return false;
    $to=json_decode((string)$message['to_json'],true)?:[];$cc=json_decode((string)$message['cc_json'],true)?:[];
    $stored=json_decode((string)($message['attachments_json']??''),true)?:[];
    $senderName=(string)($message['display_name']?:$message['email_address']);
    if(!empty($message['signature_id'])){$s=$pdo->prepare('SELECT sender_name FROM sodium_signatures WHERE id=?');$s->execute([(int)$message['signature_id']]);$senderName=(string)($s->fetchColumn()?:$senderName);}
    $domain=substr(strrchr((string)$message['email_address'],'@')?:'@localhost',1);
    $headers=['From: '.mb_encode_mimeheader($senderName).' <'.$message['email_address'].'>','To: '.implode(', ',array_map(static fn(string $mail):string=>'<'.$mail.'>',$to)),'Subject: '.mb_encode_mimeheader((string)$message['composed_subject']),'Date: '.date(DATE_RFC2822,strtotime((string)($message['composed_sent_at']?:$message['composed_created_at']))),'Message-ID: <restored-'.bin2hex(random_bytes(12)).'@'.$domain.'>','MIME-Version: 1.0'];
    if($cc)$headers[]='Cc: '.implode(', ',array_map(static fn(string $mail):string=>'<'.$mail.'>',$cc));
    $html='<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.55;color:#1f2937">'.sodium_sanitize_email_html((string)$message['composed_content']).'</div>';
    $plain=trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)>/i',"\n",$html)??$html),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    $alt='sodium_alt_'.bin2hex(random_bytes(10));$altBody='--'.$alt."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".rtrim(chunk_split(base64_encode($plain),76,"\r\n"))."\r\n--".$alt."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".rtrim(chunk_split(base64_encode($html),76,"\r\n"))."\r\n--".$alt."--\r\n";
    if($stored){$mixed='sodium_'.bin2hex(random_bytes(10));$headers[]='Content-Type: multipart/mixed; boundary="'.$mixed.'"';$body='--'.$mixed."\r\nContent-Type: multipart/alternative; boundary=\"".$alt."\"\r\n\r\n".$altBody;foreach($stored as $file){$name=str_replace(["\r","\n",'"'],'',(string)($file['name']??'piece-jointe'));$body.='--'.$mixed."\r\nContent-Type: ".($file['type']??'application/octet-stream').'; name="'.$name."\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"".$name."\"\r\n\r\n".chunk_split((string)($file['data']??''))."\r\n";}$body.='--'.$mixed."--\r\n";}else{$headers[]='Content-Type: multipart/alternative; boundary="'.$alt.'"';$body=$altBody;}
    $raw=implode("\r\n",$headers)."\r\n\r\n".$body;$sentFolder=sodium_system_folder($message,'sent');$imap=sodium_imap_open_account($message,$sentFolder);
    try{return (bool)@imap_append($imap,sodium_imap_mailbox($message,$sentFolder),$raw,"\\Seen");}finally{imap_close($imap);}
}

function sodium_sanitize_email_html(string $html, bool $blockRemoteImages = false): string
{
    $styleBlocks=[];
    if(preg_match_all('/<style\b[^>]*>(.*?)<\/style\s*>/is',$html,$styleMatches)){
        foreach($styleMatches[1] as $css){
            $css=preg_replace('/@import\b[^;]*(?:;|$)/i','',(string)$css)??'';
            $css=preg_replace('/(?:expression|behavior|-moz-binding)\s*:[^;}]*/i','',$css)??'';
            if($blockRemoteImages)$css=preg_replace('/url\s*\(\s*([\'"]?)(?:https?:)?\/\/.*?\1\s*\)/is','none',$css)??'';
            if(trim($css)!=='')$styleBlocks[]='<style>'.$css.'</style>';
        }
    }
    $html = preg_replace('/<style\b[^>]*>.*?<\/style\s*>/is', '', $html) ?? $html;
    $html = preg_replace('/<(script|head|title)\b[^>]*>.*?<\/\1\s*>/is', '', $html) ?? $html;
    $html = preg_replace('/<(meta|link)\b[^>]*\/?>/is', '', $html) ?? $html;
    $html = strip_tags($html, '<p><br><div><strong><b><em><i><u><s><ul><ol><li><a><blockquote><span><h1><h2><h3><h4><h5><h6><table><thead><tbody><tfoot><tr><th><td><hr><img>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? $html;
    $html = preg_replace('/\s+data-sodium-(?:signature(?:-spacer)?|quote)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? $html;
    $html = preg_replace('/(href\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/i', '$1#$2', $html) ?? $html;
    $html = preg_replace_callback('/<a\b([^>]*)>/i', static function(array $match): string {
        $attributes=preg_replace('/\s+(?:target|rel)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',(string)$match[1])??(string)$match[1];
        return '<a'.$attributes.' target="_blank" rel="noopener noreferrer">';
    },$html)??$html;
    $html = preg_replace('/\s+srcset\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? $html;
    $html = preg_replace('/(src\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/i', '$1$2', $html) ?? $html;
    if ($blockRemoteImages) {
        $html = preg_replace('/\s(src)\s*=\s*(["\'])((?:https?:)?\/\/.*?)\2/is', ' data-sodium-src=$2$3$2', $html) ?? $html;
        $html = preg_replace('/\ssrc\s*=\s*((?:https?:)?\/\/[^\s>]+)/is', ' data-sodium-src="$1"', $html) ?? $html;
        $html = preg_replace_callback('/\sstyle\s*=\s*(["\'])(.*?)\1/is', static function(array $match): string {
            $style = preg_replace('/(?:background(?:-image)?\s*:[^;]*url\s*\([^)]*\)\s*;?)/i', '', $match[2]) ?? '';
            return $style !== '' ? ' style=' . $match[1] . $style . $match[1] : '';
        }, $html) ?? $html;
    }
    return implode('',$styleBlocks).$html;
}

function sodium_unblock_remote_images(string $html): string
{
    return preg_replace('/\sdata-sodium-src=(["\'])(.*?)\1/is', ' src=$1$2$1', $html) ?? $html;
}

function sodium_require_aptitude(string $aptitude): void
{
    if (!sodium_can($aptitude)) {
        http_response_code(403);
        exit('Accès Sodium non autorisé.');
    }
}

function sodium_apply_global_tags_to_account(int $accountId): void
{
    global $pdo;
    sodium_ensure_schema();
    $stmt=$pdo->prepare("INSERT IGNORE INTO sodium_tags (mail_account_id,name,color,created_by,shared_key,applies_all,is_shared)
        SELECT ?,name,color,created_by,shared_key,1,is_shared FROM sodium_tag_templates WHERE applies_all=1");
    $stmt->execute([$accountId]);
}
