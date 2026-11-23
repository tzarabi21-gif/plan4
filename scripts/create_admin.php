<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/config.php';
$initial=getenv('ADMIN_INITIAL_PASSWORD');
if(!$initial || strlen($initial)<8){fwrite(STDERR,"Set ADMIN_INITIAL_PASSWORD (minimum 8 chars) in environment first.\n");exit(1);}
$mobile=getenv('ADMIN_INITIAL_MOBILE');
if(!$mobile || strlen(preg_replace('/\\D/','',$mobile))<10){fwrite(STDERR,"Set ADMIN_INITIAL_MOBILE to the super-admin mobile number.\n");exit(1);}
$name=getenv('ADMIN_INITIAL_NAME') ?: 'مدیر اصلی';
$dsn='mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset=utf8mb4';
$pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$q=$pdo->prepare("INSERT INTO users(name,mobile,password_hash,role,must_change_password) VALUES(?,?,?,'admin',1) ON DUPLICATE KEY UPDATE role='admin',password_hash=VALUES(password_hash),must_change_password=1");
$q->execute([$name,$mobile,password_hash($initial,PASSWORD_DEFAULT)]);
echo "Super admin provisioned for {$mobile}. Change the initial password after first login.\n";
