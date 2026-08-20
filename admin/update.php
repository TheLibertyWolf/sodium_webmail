<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
sodium_require_aptitude('sodium_update');
require_once __DIR__.'/../includes/layout.php';

$step=(string)($_GET['step']??'review');$error='';$stage=$_SESSION['sodium_update_stage']??null;$backup='';$migrations=[];
try{
    if($step==='migrate'&&!empty($_SESSION['sodium_update_installed'])){
        $migrations=sodium_run_pending_migrations();
        sodium_save_instance_settings(['update_checked_at'=>'','update_latest_version'=>SODIUM_VERSION,'update_last_error'=>'']);
        $backup=(string)($_SESSION['sodium_update_backup']??'');
        unset($_SESSION['sodium_update_stage'],$_SESSION['sodium_update_installed'],$_SESSION['sodium_update_backup']);
        $step='complete';
    }elseif($_SERVER['REQUEST_METHOD']==='POST'){
        $action=(string)($_POST['action']??'');
        if($action==='prepare_remote'){$status=sodium_update_status(true);if(empty($status['available'])||empty($status['download_url']))throw new RuntimeException('Aucune mise à jour GitHub n’est disponible.');$archive=sodium_update_download((string)$status['download_url']);$stage=sodium_update_stage_archive($archive);$_SESSION['sodium_update_stage']=$stage;redirect('/admin/update.php?step=confirm');}
        if($action==='prepare_upload'){$archive=sodium_update_accept_upload($_FILES['update_archive']??[]);$stage=sodium_update_stage_archive($archive);$_SESSION['sodium_update_stage']=$stage;redirect('/admin/update.php?step=confirm');}
        if($action==='install'){
            if(!is_array($stage)||!is_dir((string)($stage['source']??'')))throw new RuntimeException('La préparation de la mise à jour a expiré.');
            $backup=sodium_update_apply($stage);$_SESSION['sodium_update_installed']=1;$_SESSION['sodium_update_backup']=$backup;
            header('Location: /admin/update.php?step=migrate',true,303);exit;
        }
        if($action==='cancel'){unset($_SESSION['sodium_update_stage']);redirect('/admin/settings.php');}
    }
}catch(Throwable $exception){error_log('[Sodium update] '.$exception->getMessage());$error=$exception->getMessage();}

$status=sodium_update_status(false);
sodium_render_header('Mise à jour de Sodium');
?>
<div class="update-wizard">
    <div class="update-steps" aria-label="Étapes"><span class="<?=in_array($step,['review',''],true)?'active':''?>">1 <b>Source</b></span><span class="<?=$step==='confirm'?'active':''?>">2 <b>Contrôle</b></span><span class="<?=$step==='migrate'?'active':''?>">3 <b>Migration</b></span><span class="<?=$step==='complete'?'active':''?>">4 <b>Terminé</b></span></div>
    <?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?=e($error)?></div><?php endif;?>
    <?php if($step==='complete'):?>
        <div class="form-card text-center p-5"><i class="bi bi-check-circle-fill text-success display-3"></i><h2 class="h3 mt-3">Sodium est à jour</h2><p class="text-muted">Version installée : <code><?=e(SODIUM_VERSION)?></code></p><p><?=count($migrations)?> migration(s) SQL appliquée(s). Une sauvegarde des fichiers remplacés a été conservée dans le stockage privé de l’instance.</p><a class="btn btn-danger" href="/admin/settings.php">Retour aux paramètres généraux</a></div>
    <?php elseif($step==='confirm'&&is_array($stage)):?>
        <div class="form-card"><h2 class="h5">Contrôle avant installation</h2><div class="row g-3 my-2"><div class="col-md-6"><div class="update-version-box"><small>Version actuelle</small><strong><?=e(SODIUM_VERSION)?></strong></div></div><div class="col-md-6"><div class="update-version-box target"><small>Version préparée</small><strong><?=e($stage['version']??'inconnue')?></strong></div></div></div><div class="alert alert-warning"><strong>Une sauvegarde automatique du code remplacé sera créée.</strong> Les fichiers d’instance, secrets, uploads, journaux, caches, dépôt Git et configuration Apache ne seront jamais écrasés.</div><form method="post" class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" name="action" value="cancel">Annuler</button><button class="btn btn-danger" name="action" value="install"><i class="bi bi-cloud-arrow-down me-2"></i>Installer et migrer</button></form></div>
    <?php else:?>
        <div class="row g-4"><div class="col-lg-6"><div class="form-card h-100"><h2 class="h5"><i class="bi bi-github me-2"></i>Mise à jour automatique</h2><p class="text-muted">Télécharge la dernière version officielle publiée sur GitHub, contrôle son contenu puis prépare son installation.</p><div class="update-version-box mb-3"><small>Disponible sur GitHub</small><strong><?=e($status['latest'])?></strong></div><?php if($status['available']):?><form method="post"><button class="btn btn-danger w-100" name="action" value="prepare_remote"><i class="bi bi-download me-2"></i>Préparer la version <?=e($status['latest'])?></button></form><?php else:?><div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>Cette instance utilise déjà la dernière version.</div><?php endif;?></div></div>
        <div class="col-lg-6"><div class="form-card h-100"><h2 class="h5"><i class="bi bi-file-earmark-zip me-2"></i>Archive manuelle</h2><p class="text-muted">Utilisez une archive ZIP officielle Sodium lorsque le serveur ne peut pas télécharger GitHub directement.</p><form method="post" enctype="multipart/form-data"><label class="form-label">Archive de mise à jour</label><input class="form-control mb-3" type="file" name="update_archive" accept=".zip,application/zip" required><button class="btn btn-outline-primary w-100" name="action" value="prepare_upload">Téléverser et contrôler</button></form></div></div></div>
        <div class="alert alert-info mt-4"><i class="bi bi-shield-check me-2"></i>Le wizard refuse les chemins dangereux, les archives incomplètes, les retours à une ancienne version et les sources distantes étrangères au dépôt officiel.</div>
    <?php endif;?>
</div>
<?php sodium_render_footer(); ?>
