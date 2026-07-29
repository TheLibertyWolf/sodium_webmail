<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
$provided=(string)($_GET['token']??($_SERVER['HTTP_X_CRON_TOKEN']??''));
if(MAIL_CRON_TOKEN===''||!hash_equals(MAIL_CRON_TOKEN,$provided)){http_response_code(403);exit('Forbidden');}
header('Content-Type: application/json; charset=UTF-8');
try{echo json_encode(['ok'=>true,'result'=>mail_process_queue(50)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable $e){error_log('[Sodium cron mail] '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Traitement impossible.']);}
