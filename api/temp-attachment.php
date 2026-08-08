<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');
$userId=(int)current_user()['id'];
sodium_cleanup_temp_uploads();
if(($_POST['action']??'')==='delete'){
    $token=(string)($_POST['token']??'');
    $stmt=$pdo->prepare('SELECT storage_name FROM sodium_temp_uploads WHERE token=? AND user_id=?');$stmt->execute([$token,$userId]);$name=$stmt->fetchColumn();
    if($name!==false){@unlink(sodium_temp_upload_directory().DIRECTORY_SEPARATOR.basename((string)$name));$pdo->prepare('DELETE FROM sodium_temp_uploads WHERE token=? AND user_id=?')->execute([$token,$userId]);}
    echo json_encode(['ok'=>true]);exit;
}
$file=$_FILES['attachment']??null;
if(!$file||!is_uploaded_file((string)($file['tmp_name']??''))||(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Le fichier n’a pas pu être chargé.']);exit;}
$size=(int)$file['size'];
if($size<1||$size>25*1024*1024){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Chaque fichier doit peser au maximum 25 Mo.']);exit;}
$used=$pdo->prepare('SELECT COALESCE(SUM(file_size),0) FROM sodium_temp_uploads WHERE user_id=? AND expires_at>=NOW()');$used->execute([$userId]);
if((int)$used->fetchColumn()+$size>25*1024*1024){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Les pièces jointes dépassent 25 Mo au total.']);exit;}
$token=bin2hex(random_bytes(32));$storage=bin2hex(random_bytes(24)).'.upload';$path=sodium_temp_upload_directory().DIRECTORY_SEPARATOR.$storage;
if(!move_uploaded_file((string)$file['tmp_name'],$path)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Stockage temporaire indisponible.']);exit;}
chmod($path,0600);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';$name=mb_substr(basename((string)$file['name']),0,255);
$pdo->prepare('INSERT INTO sodium_temp_uploads(token,user_id,original_name,mime_type,file_size,storage_name,expires_at) VALUES(?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))')->execute([$token,$userId,$name,$mime,$size,$storage]);
echo json_encode(['ok'=>true,'token'=>$token,'name'=>$name,'type'=>$mime,'size'=>$size],JSON_UNESCAPED_UNICODE);
