<?php
declare(strict_types=1);

const SODIUM_VERSION = '1.3.0';
const SODIUM_UPDATE_REPOSITORY = 'TheLibertyWolf/sodium_webmail';

function sodium_update_status(bool $force=false): array
{
    $settings=sodium_instance_settings();
    $checkedAt=(string)($settings['update_checked_at']??'');
    $checkedTimestamp=$checkedAt!==''?strtotime($checkedAt):false;
    $latest=(string)($settings['update_latest_version']??SODIUM_VERSION);
    $releaseUrl=(string)($settings['update_release_url']??'');
    $downloadUrl=(string)($settings['update_download_url']??'');
    $stale=$force||$checkedTimestamp===false||$checkedTimestamp<time()-21600;
    $error='';

    if($stale&&function_exists('curl_init')){
        $curl=curl_init('https://api.github.com/repos/'.SODIUM_UPDATE_REPOSITORY.'/tags?per_page=30');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json','User-Agent: Sodium-Webmail/'.SODIUM_VERSION]]);
        $body=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        $tags=$body!==false&&$code===200?json_decode((string)$body,true):null;
        if(is_array($tags)){
            $versions=[];
            foreach($tags as $tag){$name=ltrim((string)($tag['name']??''),'vV');if(preg_match('/^\d+\.\d+\.\d+$/',$name))$versions[]=$name;}
            if($versions){usort($versions,'version_compare');$latest=(string)end($versions);$releaseUrl='https://github.com/'.SODIUM_UPDATE_REPOSITORY.'/releases/tag/v'.$latest;$downloadUrl='https://github.com/'.SODIUM_UPDATE_REPOSITORY.'/archive/refs/tags/v'.$latest.'.zip';}
            sodium_save_instance_settings(['update_checked_at'=>date('Y-m-d H:i:s'),'update_latest_version'=>$latest,'update_release_url'=>$releaseUrl,'update_download_url'=>$downloadUrl,'update_last_error'=>'']);
            $checkedAt=date('Y-m-d H:i:s');
            $settings['update_last_error']='';
        }else{
            $error='Impossible de joindre GitHub pour le moment.';
            sodium_save_instance_settings(['update_checked_at'=>date('Y-m-d H:i:s'),'update_last_error'=>$error]);
            $checkedAt=date('Y-m-d H:i:s');
        }
    }elseif($stale){$error='L’extension cURL est indisponible.';}

    return ['current'=>SODIUM_VERSION,'latest'=>$latest,'available'=>version_compare($latest,SODIUM_VERSION,'>'),'checked_at'=>$checkedAt,'release_url'=>$releaseUrl,'download_url'=>$downloadUrl,'error'=>$error?:((string)($settings['update_last_error']??''))];
}

function sodium_update_storage_directory(): string
{
    $directory=dirname(__DIR__).'/.sodium-updates';
    if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Le stockage sécurisé des mises à jour ne peut pas être créé.');
    chmod($directory,0700);
    return $directory;
}

function sodium_update_download(string $url): string
{
    if(!str_starts_with($url,'https://github.com/'.SODIUM_UPDATE_REPOSITORY.'/'))throw new RuntimeException('Source de mise à jour non autorisée.');
    $path=sodium_update_storage_directory().'/package-'.bin2hex(random_bytes(12)).'.zip';
    $handle=fopen($path,'wb');if(!$handle)throw new RuntimeException('Impossible de préparer le téléchargement.');
    $curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_FILE=>$handle,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>120,CURLOPT_FAILONERROR=>true,CURLOPT_HTTPHEADER=>['User-Agent: Sodium-Webmail/'.SODIUM_VERSION]]);$ok=curl_exec($curl);curl_close($curl);fclose($handle);
    if(!$ok||!is_file($path)||filesize($path)<1000||filesize($path)>100*1024*1024){@unlink($path);throw new RuntimeException('Le téléchargement de la mise à jour a échoué.');}
    return $path;
}

function sodium_update_accept_upload(array $upload): string
{
    if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new RuntimeException('Archive de mise à jour invalide ou absente.');
    if((int)($upload['size']??0)>100*1024*1024)throw new RuntimeException('L’archive dépasse la limite de 100 Mo.');
    $path=sodium_update_storage_directory().'/package-'.bin2hex(random_bytes(12)).'.zip';
    if(!move_uploaded_file((string)$upload['tmp_name'],$path))throw new RuntimeException('Impossible de stocker l’archive de mise à jour.');
    chmod($path,0600);return $path;
}

function sodium_update_stage_archive(string $archive): array
{
    if(!class_exists('ZipArchive'))throw new RuntimeException('L’extension ZIP est requise.');
    $zip=new ZipArchive();if($zip->open($archive)!==true)throw new RuntimeException('L’archive ZIP ne peut pas être lue.');
    $stage=sodium_update_storage_directory().'/stage-'.bin2hex(random_bytes(12));mkdir($stage,0700,true);$total=0;
    try{
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);$name=str_replace('\\','/',(string)($stat['name']??''));
            if($name===''||str_starts_with($name,'/')||preg_match('#(^|/)\.\.(/|$)#',$name))throw new RuntimeException('L’archive contient un chemin interdit.');
            $total+=(int)($stat['size']??0);if($total>250*1024*1024)throw new RuntimeException('Le contenu décompressé dépasse 250 Mo.');
            $target=$stage.'/'.$name;
            if(str_ends_with($name,'/')){if(!is_dir($target))mkdir($target,0700,true);continue;}
            if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);
            $input=$zip->getStream((string)($stat['name']??''));$output=fopen($target,'wb');if(!$input||!$output)throw new RuntimeException('Un fichier de l’archive ne peut pas être extrait.');stream_copy_to_stream($input,$output);fclose($input);fclose($output);chmod($target,0600);
        }
    }finally{$zip->close();}
    $entries=array_values(array_filter(scandir($stage)?:[],static fn(string $name):bool=>!in_array($name,['.','..'],true)));
    $source=$stage;if(count($entries)===1&&is_dir($stage.'/'.$entries[0]))$source=$stage.'/'.$entries[0];
    foreach(['config.php','includes/sodium.php','package.json'] as $required)if(!is_file($source.'/'.$required))throw new RuntimeException('Cette archive n’est pas une distribution Sodium complète.');
    $package=json_decode((string)file_get_contents($source.'/package.json'),true);$version=(string)($package['version']??'');
    if(($package['name']??'')!=='sodium-webmail')throw new RuntimeException('Cette archive ne correspond pas au produit Sodium Webmail.');
    if(!preg_match('/^\d+\.\d+\.\d+$/',$version))throw new RuntimeException('La version de l’archive est illisible.');
    if(version_compare($version,SODIUM_VERSION,'<'))throw new RuntimeException('Le retour vers une version antérieure est interdit.');
    return ['archive'=>$archive,'stage'=>$stage,'source'=>$source,'version'=>$version];
}

function sodium_update_path_is_protected(string $relative): bool
{
    $relative=ltrim(str_replace('\\','/',$relative),'/');
    foreach(['config.local.php','.installed','.sodium-mail-key','.env','.git','.sodium-updates','uploads','logs','cache','tmp','.htaccess'] as $protected)if($relative===$protected||str_starts_with($relative,$protected.'/'))return true;
    return false;
}

function sodium_update_collect_files(string $source): array
{
    $files=[];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){if(!$file->isFile()||$file->isLink())continue;$relative=str_replace('\\','/',substr($file->getPathname(),strlen($source)+1));if(!sodium_update_path_is_protected($relative))$files[$relative]=$file->getPathname();}
    return $files;
}

function sodium_update_apply(array $stage): string
{
    $root=dirname(__DIR__);$files=sodium_update_collect_files((string)$stage['source']);if(!$files)throw new RuntimeException('Aucun fichier à installer.');
    $backup=sodium_update_storage_directory().'/backup-'.date('Ymd-His').'-'.SODIUM_VERSION.'.zip';$zip=new ZipArchive();if($zip->open($backup,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('La sauvegarde de sécurité ne peut pas être créée.');
    foreach(array_keys($files) as $relative)if(is_file($root.'/'.$relative))$zip->addFile($root.'/'.$relative,$relative);$zip->close();chmod($backup,0600);
    $lock=fopen(sodium_update_storage_directory().'/update.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Une autre mise à jour est déjà en cours.');
    try{foreach($files as $relative=>$source){$target=$root.'/'.$relative;if(!is_dir(dirname($target))&&!mkdir(dirname($target),0755,true)&&!is_dir(dirname($target)))throw new RuntimeException('Impossible de créer un dossier de destination.');$temporary=$target.'.sodium-new';if(!copy($source,$temporary))throw new RuntimeException('Impossible d’installer '.$relative.'.');chmod($temporary,0644);if(!rename($temporary,$target))throw new RuntimeException('Impossible de finaliser '.$relative.'.');}}finally{flock($lock,LOCK_UN);fclose($lock);}
    return $backup;
}

function sodium_run_pending_migrations(): array
{
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS sodium_migrations (migration VARCHAR(190) PRIMARY KEY,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $applied=$pdo->query('SELECT migration FROM sodium_migrations')->fetchAll(PDO::FETCH_COLUMN);$done=[];
    foreach(glob(dirname(__DIR__).'/migrations/*.php')?:[] as $file){$name=basename($file);if(in_array($name,$applied,true))continue;$migration=require $file;if(!is_callable($migration))throw new RuntimeException('Migration invalide : '.$name);$pdo->beginTransaction();try{$migration($pdo);$pdo->prepare('INSERT INTO sodium_migrations(migration) VALUES(?)')->execute([$name]);$pdo->commit();$done[]=$name;}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw new RuntimeException('La migration '.$name.' a échoué.');}}
    return $done;
}
