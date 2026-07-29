<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_login();
sodium_require_aptitude('licence');

$canManage=sodium_can('licence');
if($_SERVER['REQUEST_METHOD']==='POST'&&$canManage){
    $licenseKey=strtolower(trim((string)($_POST['license_key']??'')));
    $verification=sodium_verify_license_key($licenseKey);
    if(($verification['status']??'')==='ok'){
        sodium_store_license($licenseKey,$verification);
        flash('success','Licence enregistrée et activée.');
        redirect('/admin/license.php');
    }
    flash('danger','Activation refusée : '.(string)($verification['message']??'clé invalide.'));
    redirect('/admin/license.php');
}

$license=sodium_license_public_info();

require_once dirname(__DIR__).'/includes/layout.php';
sodium_render_header('Licence');
?>
<div class="row g-4">
    <div class="col-12"><div class="table-card"><div class="p-4 border-bottom"><div class="d-flex align-items-center gap-3"><span class="brand-mark">M</span><div><h2 class="h4 mb-1">Licence Sodium Webmail</h2><p class="text-muted mb-0">Activation et contrôle du droit d’exploitation de cette instance.</p></div></div></div><div class="p-4">
        <?php if(!$license['is_configured']): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Aucune clé enregistrée.</strong> En dehors de la connexion et de cette page, Sodium est actuellement verrouillé.</div><?php elseif($license['is_valid']): ?><div class="alert alert-success"><i class="bi bi-patch-check-fill me-2"></i>Licence active et vérifiée.</div><?php else: ?><div class="alert alert-danger"><i class="bi bi-x-octagon-fill me-2"></i>La licence enregistrée n’est pas valide ou a expiré.</div><?php endif; ?>
        <dl class="row mb-0">
            <dt class="col-sm-5">Produit</dt><dd class="col-sm-7"><?=e($license['product_name']??'Sodium Webmail')?></dd>
            <dt class="col-sm-5">Statut</dt><dd class="col-sm-7"><span class="badge <?=$license['is_valid']?'text-bg-success':'text-bg-warning'?>"><?=e($license['is_valid']?'Active':'Activation requise')?></span></dd>
            <dt class="col-sm-5">Type</dt><dd class="col-sm-7"><?=e($license['license_type']??'—')?></dd>
            <dt class="col-sm-5">Ayant droit</dt><dd class="col-sm-7"><?=e($license['rights_holder']??'—')?></dd>
            <dt class="col-sm-5">Domaine autorisé</dt><dd class="col-sm-7"><code><?=e($license['allowed_domain']??'—')?></code></dd>
            <dt class="col-sm-5">Expiration</dt><dd class="col-sm-7"><?=!empty($license['expires_at'])&&$license['expires_at']>='9999-01-01'?'Perpétuelle':e($license['expires_at']??'—')?></dd>
            <dt class="col-sm-5">Enregistrement</dt><dd class="col-sm-7"><?=e($license['registered_at']??'—')?></dd>
            <dt class="col-sm-5">Dernière vérification</dt><dd class="col-sm-7"><?=e($license['last_checked_at']??'Jamais')?></dd>
        </dl>
    </div></div></div>
    <div class="col-12"><div class="table-card"><div class="p-4 border-bottom"><h2 class="h5 mb-1">Enregistrer une clé</h2><p class="text-muted small mb-0">La clé est chiffrée avant son stockage dans Sodium.</p></div><?php if($canManage): ?><form method="post" class="p-4"><label class="form-label">Clé de licence</label><textarea class="form-control font-monospace" name="license_key" rows="4" maxlength="128" minlength="128" spellcheck="false" required placeholder="128 caractères hexadécimaux"></textarea><div class="form-text mb-3">La clé sera vérifiée auprès de licence.jessysystem.com pour ce domaine.</div><button class="btn btn-danger w-100"><i class="bi bi-key me-2"></i>Vérifier et enregistrer</button></form><?php else: ?><div class="p-4"><div class="alert alert-info mb-0"><i class="bi bi-info-circle-fill me-2"></i>Seul un utilisateur disposant de l’aptitude <strong>Licence</strong> peut enregistrer la clé. Contactez votre administrateur.</div></div><?php endif; ?></div></div>
</div>
<?php sodium_render_footer(); ?>
