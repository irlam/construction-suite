<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('SUITE_TEST_MYSQL')!=='1' || getenv('DB_DRIVER')!=='mysql'
    || !str_starts_with((string)getenv('DB_DATABASE'),'suite_test_')) {http_response_code(404);exit(1);}
session_save_path(sys_get_temp_dir());session_start();
register_shutdown_function(static function():void {$id=session_id();session_write_close();if($id!=='')@unlink(sys_get_temp_dir().'/sess_'.$id);});
require_once dirname(__DIR__).'/app/bootstrap.php';
$input=json_decode((string)fgets(STDIN),true,8,JSON_THROW_ON_ERROR);
fwrite(STDOUT,"READY\n");
if(trim((string)fgets(STDIN))!=='GO')exit(1);
try {
    $id=suite_invitations()->accept($input['token'],null,'Invited worker',$input['password']);
    fwrite(STDOUT,json_encode(['accepted'=>$id>0])."\n");
} catch (RuntimeException $e) {fwrite(STDOUT,"{\"accepted\":false}\n");}
