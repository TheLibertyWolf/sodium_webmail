<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
sodium_require_aptitude('sodium_full_access');
require_once __DIR__.'/../includes/layout.php';

$settings=sodium_instance_settings();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $enabled=!empty($_POST['turnstile_enabled']);
    $siteKey=array_key_exists('turnstile_site_key',$_POST)?trim((string)$_POST['turnstile_site_key']):trim((string)($settings['turnstile_site_key']??''));
    $secret=trim((string)($_POST['turnstile_secret_key']??''));
    $existingSecret=sodium_turnstile_secret_key();
    if($enabled&&($siteKey===''||($secret===''&&$existingSecret===''))){flash('danger','La clé de site et la clé secrète sont obligatoires lorsque Turnstile est activé.');redirect('/admin/security.php');}
    $values=['turnstile_enabled'=>$enabled?'1':'0','turnstile_site_key'=>$siteKey];
    if($secret!=='')$values['turnstile_secret_cipher']=sodium_encrypt_secret($secret);
    sodium_save_instance_settings($values);
    flash('success',$enabled?'Cloudflare Turnstile est activé.':'Cloudflare Turnstile est désactivé.');redirect('/admin/security.php');
}
$settings=sodium_instance_settings();
$enabled=sodium_turnstile_enabled();
$secretConfigured=sodium_turnstile_secret_key()!=='';
sodium_render_header('Paramètres de sécurité');
?>
<form method="post" class="row g-4">
    <div class="col-12"><div class="form-card">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3"><div><h2 class="h5 mb-1">Cloudflare Turnstile</h2><p class="text-muted mb-0">Protection anti-robots appliquée à l’authentification et à la récupération de mot de passe.</p></div><span class="badge text-bg-<?=$enabled?'success':'secondary'?> fs-6"><?=$enabled?'Activé':'Désactivé'?></span></div>
        <div class="alert alert-info"><i class="bi bi-shield-check me-2"></i>Turnstile est facultatif, mais vivement recommandé pour limiter les attaques automatisées et le bourrage d’identifiants.</div>
        <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" role="switch" id="turnstileEnabled" name="turnstile_enabled" value="1" <?=$enabled?'checked':''?>><label class="form-check-label" for="turnstileEnabled"><strong>Activer Turnstile sur les pages publiques</strong></label></div>
        <div id="turnstileFields" class="row g-3">
            <div class="col-md-6"><label class="form-label">Clé de site</label><input class="form-control font-monospace" name="turnstile_site_key" value="<?=e($settings['turnstile_site_key']??'')?>"><div class="form-text">Clé publique fournie par Cloudflare pour le domaine de cette instance.</div></div>
            <div class="col-md-6"><label class="form-label">Clé secrète</label><input class="form-control font-monospace" type="password" name="turnstile_secret_key" autocomplete="new-password" placeholder="<?=$secretConfigured?'Configurée — laisser vide pour conserver':'Renseigner la clé secrète'?>"><div class="form-text">La clé est chiffrée avant son stockage et n’est jamais réaffichée.</div></div>
        </div>
    </div></div>
    <div class="col-12"><button class="btn btn-danger" type="submit"><i class="bi bi-shield-lock me-2"></i>Enregistrer la sécurité</button></div>
</form>
<script>(()=>{const toggle=document.getElementById('turnstileEnabled'),fields=document.getElementById('turnstileFields');const sync=()=>{fields.style.opacity=toggle.checked?'1':'.55';fields.querySelectorAll('input').forEach(input=>input.disabled=!toggle.checked);};toggle.addEventListener('change',sync);sync();})();</script>
<?php sodium_render_footer();?>
