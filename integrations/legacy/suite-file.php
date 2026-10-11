<?php
declare(strict_types=1);
require_once __DIR__.'/.suite/gate.php';
$path=$_GET['file']??null;
if(!is_string($path)||!preg_match('~^(?:uploads|export|pdfs)/[a-zA-Z0-9_./ -]+$~D',$path)||str_contains($path,'..')){http_response_code(404);exit;}
$root=realpath(__DIR__);$file=realpath(__DIR__.'/'.$path);
if(!$root||!$file||!str_starts_with($file,$root.'/')||!is_file($file)){http_response_code(404);exit;}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);
if(!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf','text/csv','text/plain'],true)){http_response_code(404);exit;}
header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($file);
