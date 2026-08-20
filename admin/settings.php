<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
sodium_require_aptitude('sodium_full_access');
require_once __DIR__.'/../includes/layout.php';

$settings=sodium_instance_settings();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $accountId=array_key_exists('system_mail_account_id',$_POST)?(int)$_POST['system_mail_account_id']:(int)($settings['system_mail_account_id']??0);
    if($accountId){$check=$pdo->prepare('SELECT COUNT(*) FROM sodium_mail_accounts WHERE id=?');$check->execute([$accountId]);if(!$check->fetchColumn())$accountId=0;}
    $timezone=in_array($_POST['timezone']??'',['Europe/Paris','UTC','Europe/Brussels','Europe/Luxembourg','Europe/Zurich'],true)?(string)$_POST['timezone']:'Europe/Paris';
    $transport=in_array($_POST['system_mail_transport']??'',['smtp','brevo','php'],true)?(string)$_POST['system_mail_transport']:'smtp';
    $dependencySource=in_array($_POST['dependency_source']??'',['local','remote'],true)?(string)$_POST['dependency_source']:'local';
    $brevoEmail=array_key_exists('system_brevo_from_email',$_POST)?trim((string)$_POST['system_brevo_from_email']):trim((string)($settings['system_brevo_from_email']??''));
    $brevoKey=trim((string)($_POST['system_brevo_api_key']??''));
    $savedSettings=[
        'instance_name'=>trim((string)($_POST['instance_name']??'Sodium'))?:'Sodium',
        'organization_name'=>trim((string)($_POST['organization_name']??'')),
        'support_email'=>trim((string)($_POST['support_email']??'')),
        'system_mail_account_id'=>(string)$accountId,
        'system_sender_name'=>trim((string)($_POST['system_sender_name']??'Sodium'))?:'Sodium',
        'system_mail_transport'=>$transport,
        'system_brevo_from_email'=>$brevoEmail,
        'timezone'=>$timezone,
        'dependency_source'=>$dependencySource,
    ];
    if($brevoKey!=='')$savedSettings['system_brevo_api_cipher']=sodium_encrypt_secret($brevoKey);
    sodium_save_instance_settings($savedSettings);
    flash('success','Paramètres généraux enregistrés.');redirect('/admin/settings.php');
}
$accounts=$pdo->query("SELECT id,display_name,email_address FROM sodium_mail_accounts WHERE account_status='active' ORDER BY display_name,email_address")->fetchAll();
$settings=sodium_instance_settings();
$systemTransport=sodium_system_mail_transport();
$brevoConfigured=sodium_system_brevo_api_key()!=='';
$cronUrl=rtrim(BASE_URL,'/').'/cron/send-mail-queue.php?token='.rawurlencode(MAIL_CRON_TOKEN);
$cronCommand='* * * * * curl --fail --silent --show-error '.escapeshellarg($cronUrl).' >/dev/null';
$lastCronAt=trim((string)($settings['cron_last_run_at']??''));
$lastCronTimestamp=$lastCronAt!==''?strtotime($lastCronAt):false;
$cronAge=$lastCronTimestamp!==false?time()-$lastCronTimestamp:null;
$cronFailed=($settings['cron_last_status']??'')==='failed';
$cronState=$cronFailed?'danger':($cronAge!==null&&$cronAge<=180?'success':($cronAge!==null&&$cronAge<=600?'warning':'danger'));
$cronLabel=$cronFailed?'Dernière exécution en échec':($cronState==='success'?'Opérationnel':($cronState==='warning'?'Exécution en retard':'Non détecté'));
sodium_render_header('Paramètres généraux');
?>
<form method="post" class="row g-4">
    <div class="col-12"><div class="form-card"><h2 class="h5 mb-3">Instance Sodium</h2><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nom de l’instance</label><input class="form-control" name="instance_name" value="<?=e($settings['instance_name'])?>" required></div>
        <div class="col-md-6"><label class="form-label">Organisation titulaire</label><input class="form-control" name="organization_name" value="<?=e($settings['organization_name'])?>"></div>
        <div class="col-md-6"><label class="form-label">Adresse de support</label><input class="form-control" type="email" name="support_email" value="<?=e($settings['support_email'])?>"></div>
        <div class="col-md-6"><label class="form-label">Fuseau horaire</label><select class="form-select" name="timezone"><?php foreach(['Europe/Paris','UTC','Europe/Brussels','Europe/Luxembourg','Europe/Zurich'] as $zone):?><option <?=$settings['timezone']===$zone?'selected':''?>><?=e($zone)?></option><?php endforeach;?></select></div>
    </div></div></div>
    <div class="col-12"><div class="form-card"><h2 class="h5 mb-1">Envoi des notifications système</h2><p class="text-muted">Transport utilisé pour les notifications et les codes de récupération de mot de passe.</p><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Méthode d’envoi</label><select class="form-select" name="system_mail_transport" id="systemMailTransport"><option value="smtp" <?=$systemTransport==='smtp'?'selected':''?>>Compte mail Sodium — SMTP</option><option value="brevo" <?=$systemTransport==='brevo'?'selected':''?>>API Brevo</option><option value="php" <?=$systemTransport==='php'?'selected':''?>>Fonction PHP mail()</option></select></div>
        <div class="col-md-6"><label class="form-label">Nom d’expéditeur système</label><input class="form-control" name="system_sender_name" value="<?=e($settings['system_sender_name'])?>"></div>
        <div class="col-12 system-transport-fields" data-transport="smtp"><label class="form-label">Compte mail expéditeur</label><select class="form-select" name="system_mail_account_id"><option value="0">Non configuré</option><?php foreach($accounts as $account):?><option value="<?=(int)$account['id']?>" <?=(int)$settings['system_mail_account_id']===(int)$account['id']?'selected':''?>><?=e($account['display_name']?:$account['email_address'])?> — <?=e($account['email_address'])?></option><?php endforeach;?></select></div>
        <div class="col-12 system-transport-fields" data-transport="brevo"><div class="row g-3"><div class="col-md-6"><label class="form-label">Adresse expéditeur Brevo</label><input class="form-control" type="email" name="system_brevo_from_email" value="<?=e($settings['system_brevo_from_email']??'')?>" placeholder="no-reply@example.com"></div><div class="col-md-6"><label class="form-label">Clé API Brevo</label><input class="form-control font-monospace" type="password" name="system_brevo_api_key" autocomplete="new-password" placeholder="<?=$brevoConfigured?'Configurée — laisser vide pour conserver':'Renseigner la clé API'?>"><div class="form-text">La clé est chiffrée et ne sera jamais réaffichée.</div></div></div></div>
        <div class="col-12 system-transport-fields" data-transport="php"><div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>La fonction PHP <code>mail()</code> dépend de la configuration de l’hébergeur. Vérifiez SPF, DKIM et la délivrabilité du domaine avant de l’utiliser en production.</div></div>
    </div></div></div>
    <div class="col-12"><div class="form-card"><h2 class="h5 mb-1">Utilisation des dépendances</h2><p class="text-muted mb-3">Choisissez comment Sodium charge Bootstrap et Bootstrap Icons. Le mode local est recommandé et utilisé par défaut.</p><div class="row g-3">
        <div class="col-lg-6"><input class="btn-check" type="radio" name="dependency_source" id="dependencyLocal" value="local" <?=($settings['dependency_source']??'local')==='local'?'checked':''?>><label class="dependency-choice border rounded-3 p-3 h-100 d-block" for="dependencyLocal"><span class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-hdd-stack text-success fs-4"></i><strong>Fichiers locaux <span class="badge text-bg-success ms-1">Recommandé</span></strong></span><span class="text-muted small d-block">Sodium reste autonome, plus prévisible et ne contacte aucun fournisseur tiers pour son interface. Ce mode utilise un peu d’espace disque et la bande passante du serveur.</span></label></div>
        <div class="col-lg-6"><input class="btn-check" type="radio" name="dependency_source" id="dependencyRemote" value="remote" <?=($settings['dependency_source']??'local')==='remote'?'checked':''?>><label class="dependency-choice border rounded-3 p-3 h-100 d-block" for="dependencyRemote"><span class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-cloud-arrow-down text-info fs-4"></i><strong>CDN distant</strong></span><span class="text-muted small d-block">Peut réduire le trafic local grâce au cache du CDN, mais dépend de jsDelivr. L’adresse IP et des métadonnées techniques du visiteur peuvent être transmises à ce fournisseur.</span></label></div>
        <div class="col-12"><div class="alert alert-info mb-0"><i class="bi bi-shield-check me-2"></i>En mode distant, Sodium bascule automatiquement sur les fichiers locaux si le CDN ne répond pas.</div></div>
    </div></div></div>
    <div class="col-12"><button class="btn btn-danger" type="submit"><i class="bi bi-check-lg me-2"></i>Enregistrer</button></div>
</form>

<div class="form-card mt-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div><h2 class="h5 mb-1">Tâche planifiée</h2><p class="text-muted mb-0">Le cron traite chaque minute les envois programmés, les files d’attente et les opérations différées.</p></div>
        <span class="badge text-bg-<?=$cronState?> fs-6"><i class="bi bi-<?=$cronState==='success'?'check-circle':'exclamation-triangle'?> me-1"></i><?=$cronLabel?></span>
    </div>
    <div class="mb-3"><label class="form-label">URL sécurisée du cron</label><div class="input-group"><input class="form-control font-monospace" id="cronUrl" value="<?=e($cronUrl)?>" readonly><button class="btn btn-outline-secondary" type="button" data-copy-target="#cronUrl"><i class="bi bi-copy"></i> Copier</button></div><div class="form-text">Cette URL contient un jeton confidentiel : ne la communiquez pas et ne la placez pas dans un dépôt public.</div></div>
    <div class="mb-3"><label class="form-label">Exemple cPanel — une exécution par minute</label><div class="input-group"><input class="form-control font-monospace" id="cronCommand" value="<?=e($cronCommand)?>" readonly><button class="btn btn-outline-secondary" type="button" data-copy-target="#cronCommand"><i class="bi bi-copy"></i> Copier</button></div></div>
    <?php if($lastCronTimestamp!==false): ?><div class="small text-muted">Dernière exécution reçue : <strong><?=e(date('d/m/Y à H:i:s',$lastCronTimestamp))?></strong><?=($settings['cron_last_status']??'')==='failed'?' — la dernière exécution a échoué.':''?></div><?php else: ?><div class="alert alert-warning mb-0"><i class="bi bi-clock-history me-2"></i>Aucune exécution n’a encore été reçue. Ajoutez la commande dans cPanel, puis attendez environ une minute.</div><?php endif; ?>
</div>
<script>document.querySelectorAll('[data-copy-target]').forEach(button=>button.addEventListener('click',async()=>{const input=document.querySelector(button.dataset.copyTarget);if(!input)return;await navigator.clipboard.writeText(input.value);button.innerHTML='<i class="bi bi-check-lg"></i> Copié';setTimeout(()=>button.innerHTML='<i class="bi bi-copy"></i> Copier',1500);}));(()=>{const select=document.getElementById('systemMailTransport'),groups=document.querySelectorAll('.system-transport-fields');const sync=()=>groups.forEach(group=>{const active=group.dataset.transport===select.value;group.classList.toggle('d-none',!active);group.querySelectorAll('input,select').forEach(input=>input.disabled=!active);});select.addEventListener('change',sync);sync();})();</script>
<?php sodium_render_footer();?>
