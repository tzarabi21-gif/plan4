<?php
declare(strict_types=1);
session_start();
$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['timezone']);
try {
 $dsn = 'mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset'];
 $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (Throwable $e) { $pdo = null; }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function json_out(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_user(): array { $u=current_user(); if(!$u) json_out(['ok'=>false,'message'=>'ابتدا وارد حساب شوید'],401); return $u; }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
$action = $_GET['api'] ?? null;
if ($action) {
 if (!$pdo) json_out(['ok'=>false,'message'=>'اتصال پایگاه داده برقرار نیست. schema.sql را اجرا و تنظیمات DB را بررسی کنید.'],503);
 $body=json_decode(file_get_contents('php://input'),true) ?: $_POST;
 if ($_SERVER['REQUEST_METHOD']==='POST' && !in_array($action,['login','register','request-otp','verify-otp'],true)) { if(!hash_equals($_SESSION['csrf']??'', $body['_csrf']??'')) json_out(['ok'=>false,'message'=>'درخواست نامعتبر است'],419); }
 try {
  if($action==='me'){ $u=current_user(); json_out(['ok'=>true,'user'=>$u,'csrf'=>csrf()]); }
  if($action==='login'){
   $q=$pdo->prepare('SELECT id,name,mobile,password_hash,role,grade,section,calendar,xp,level,is_active FROM users WHERE mobile=? LIMIT 1');$q->execute([trim($body['mobile']??'')]);$u=$q->fetch();
   if(!$u || !password_verify((string)($body['password']??''),$u['password_hash']) || !$u['is_active']) json_out(['ok'=>false,'message'=>'شماره موبایل یا رمز عبور صحیح نیست']);
   unset($u['password_hash']);session_regenerate_id(true);$_SESSION['user']=$u;$_SESSION['csrf']=bin2hex(random_bytes(24));json_out(['ok'=>true,'user'=>$u,'csrf'=>csrf()]);
  }
  if($action==='logout'){$_SESSION=[];session_destroy();json_out(['ok'=>true]);}
  if($action==='register'){
   $mobile=preg_replace('/\D/','',(string)($body['mobile']??''));$pass=(string)($body['password']??'');$name=trim((string)($body['name']??''));
   if(strlen($mobile)<10 || strlen($pass)<10 || $name==='') json_out(['ok'=>false,'message'=>'نام، شماره معتبر و رمز حداقل ۱۰ کاراکتری لازم است']);
   $q=$pdo->prepare('INSERT INTO users(name,mobile,password_hash,role,grade,section) VALUES(?,?,?,\'student\',?,?)');$q->execute([$name,$mobile,password_hash($pass,PASSWORD_DEFAULT),max(1,min(12,(int)($body['grade']??7))),($body['section']??'middle')]);
   json_out(['ok'=>true,'message'=>'حساب ساخته شد. برای فعال‌سازی عمومی، تأیید OTP را در تنظیمات پیامک فعال کنید.']);
  }
  if($action==='dashboard'){
   $u=require_user();$id=$u['id'];$stats=[];
   foreach(['tasks'=>'تکالیف','exams'=>'امتحان‌ها','grades'=>'نمرات','notes'=>'یادداشت‌ها','flashcards'=>'فلش‌کارت‌ها'] as $t=>$label){$q=$pdo->prepare("SELECT COUNT(*) c FROM {$t} WHERE user_id=?");$q->execute([$id]);$stats[$t]=(int)$q->fetch()['c'];}
   $q=$pdo->prepare("SELECT * FROM tasks WHERE user_id=? ORDER BY created_at DESC LIMIT 8");$q->execute([$id]);$tasks=$q->fetchAll();
   $q=$pdo->prepare("SELECT * FROM exams WHERE user_id=? ORDER BY exam_at ASC LIMIT 5");$q->execute([$id]);$exams=$q->fetchAll();
   json_out(['ok'=>true,'stats'=>$stats,'tasks'=>$tasks,'exams'=>$exams]);
  }
  if($action==='tasks' && $_SERVER['REQUEST_METHOD']==='GET'){$u=require_user();$q=$pdo->prepare('SELECT * FROM tasks WHERE user_id=? ORDER BY created_at DESC');$q->execute([$u['id']]);json_out(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='tasks' && $_SERVER['REQUEST_METHOD']==='POST'){$u=require_user();$title=trim((string)($body['title']??''));if($title==='')json_out(['ok'=>false,'message'=>'عنوان لازم است']);$q=$pdo->prepare('INSERT INTO tasks(user_id,title,subject,due_at) VALUES(?,?,?,?)');$q->execute([$u['id'],$title,$body['subject']??null,$body['due_at']??null]);json_out(['ok'=>true,'id'=>$pdo->lastInsertId()]);}
  if($action==='task-status'){$u=require_user();$q=$pdo->prepare("UPDATE tasks SET status=? WHERE id=? AND user_id=?");$q->execute([$body['status']==='done'?'done':'doing',(int)$body['id'],$u['id']]);json_out(['ok'=>true]);}
  if($action==='admin-overview'){$u=require_user();if(!in_array($u['role'],['admin','subadmin'],true))json_out(['ok'=>false,'message'=>'دسترسی غیرمجاز'],403);$counts=[];foreach(['users','study_plans','tasks','exams','groups_nexa','group_reports'] as $t){$counts[$t]=(int)$pdo->query("SELECT COUNT(*) c FROM {$t}")->fetch()['c'];}$reports=$pdo->query("SELECT r.*,g.title group_title FROM group_reports r JOIN groups_nexa g ON g.id=r.group_id ORDER BY r.created_at DESC LIMIT 10")->fetchAll();json_out(['ok'=>true,'counts'=>$counts,'reports'=>$reports]);}
  if($action==='admin-users'){$u=require_user();if($u['role']!=='admin')json_out(['ok'=>false,'message'=>'فقط مدیر اصلی مجاز است'],403);$rows=$pdo->query('SELECT id,name,mobile,role,grade,is_active,created_at FROM users ORDER BY id DESC LIMIT 200')->fetchAll();json_out(['ok'=>true,'items'=>$rows]);}
  if($action==='admin-create-subadmin'){$u=require_user();if($u['role']!=='admin')json_out(['ok'=>false,'message'=>'فقط مدیر اصلی مجاز است'],403);$mobile=preg_replace('/\D/','',(string)($body['mobile']??''));$pass=(string)($body['password']??'');if(strlen($mobile)<10||strlen($pass)<12)json_out(['ok'=>false,'message'=>'شماره و رمز حداقل ۱۲ کاراکتری لازم است']);$q=$pdo->prepare("INSERT INTO users(name,mobile,password_hash,role) VALUES(?,?,?,'subadmin')");$q->execute([trim($body['name']??'مدیر فرعی'),$mobile,password_hash($pass,PASSWORD_DEFAULT)]);json_out(['ok'=>true]);}
  if($action==='save-setting'){$u=require_user();if($u['role']!=='admin')json_out(['ok'=>false,'message'=>'فقط مدیر اصلی مجاز است'],403);$key=preg_replace('/[^a-z0-9_]/i','',(string)($body['key']??''));$val=(string)($body['value']??'');$q=$pdo->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$q->execute([$key,$val]);json_out(['ok'=>true]);}
  json_out(['ok'=>false,'message'=>'مسیر API شناخته نشد'],404);
 } catch(Throwable $e){json_out(['ok'=>false,'message'=>$config['debug']?$e->getMessage():'خطای داخلی سرور'],500);}
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="NEXA؛ سامانه هوشمند برنامه‌ریزی درسی، مدیریت تکالیف، آزمون و پیشرفت تحصیلی دانش‌آموزان."><meta name="theme-color" content="#080d1d"><title>NEXA | داشبورد هوشمند دانش‌آموز</title><link rel="manifest" href="manifest.json"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell"><aside class="sidebar"><div class="brand"><span class="brand-mark">◈</span><span>NEXA</span></div><div class="profile-mini"><div class="avatar">ن</div><div><b id="sideName">دانش‌آموز</b><small id="sideRole">حساب مهمان</small></div></div><nav id="nav"><button class="nav-item active" data-page="dashboard">⌂ <span>داشبورد</span></button><button class="nav-item" data-page="planner">▦ <span>برنامه‌ریزی درسی</span></button><button class="nav-item" data-page="tasks">☑ <span>وظایف و تکالیف</span></button><button class="nav-item" data-page="calendar">▣ <span>تقویم و یادآورها</span></button><button class="nav-item" data-page="exams">✎ <span>مرکز امتحانات</span></button><button class="nav-item" data-page="grades">◉ <span>نمرات و عملکرد</span></button><button class="nav-item" data-page="library">▤ <span>جزوه و فایل‌ها</span></button><button class="nav-item" data-page="flashcards">▱ <span>فلش‌کارت و مرور</span></button><button class="nav-item" data-page="timer">◷ <span>تایمر مطالعه</span></button><button class="nav-item" data-page="notes">✧ <span>دفترچه یادداشت</span></button><button class="nav-item" data-page="groups">♧ <span>گروه‌های مطالعه</span></button><button class="nav-item" data-page="goals">✦ <span>هدف‌ها و عادت‌ها</span></button><button class="nav-item" data-page="assistant">✺ <span>دستیار NEXA</span></button><button class="nav-item" data-page="parent">♙ <span>دسترسی والدین</span></button><button class="nav-item admin-only hidden" data-page="admin">⚙ <span>پنل مدیریت</span></button></nav><div class="sidebar-bottom"><span class="status-dot"></span> <small>نسخه پایه NEXA</small><button id="logoutBtn" class="logout">خروج از حساب</button></div></aside>
<main class="main"><header class="topbar"><div><div class="eyebrow">فضای یادگیری هوشمند</div><h1 id="pageTitle">داشبورد دانش‌آموز</h1></div><div class="top-actions"><button class="icon-btn" title="اعلان‌ها">♧</button><button class="icon-btn" id="themeBtn" title="تغییر تم">☾</button><button class="user-chip" id="authBtn"><span class="avatar small">ن</span><span id="authLabel">ورود / ثبت‌نام</span></button></div></header><section id="content" class="content"></section><footer>© NEXA · مسیر پیشرفت تو از همین‌جا شروع می‌شود <span>نسخه ۱.۰</span></footer></main></div>
<div id="modal" class="modal hidden"><div class="modal-card"><button class="close" id="closeModal">×</button><div id="modalBody"></div></div></div><div id="toast" class="toast"></div><script>window.NEXA={csrf:<?=json_encode(csrf())?>,base:'',loggedIn:<?=json_encode((bool)current_user())?>,user:<?=json_encode(current_user(),JSON_UNESCAPED_UNICODE)?>};</script><script src="assets/app.js"></script></body></html>
