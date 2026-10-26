<?php
declare(strict_types=1);
require __DIR__.'/../app/Auth.php';
require __DIR__.'/../app/Users.php';
require __DIR__.'/../app/Records.php';
require __DIR__.'/../app/WorkOrders.php';
function check(bool $ok,string $message):void { if(!$ok)throw new LogicException($message); }
function denied(callable $f):void {try{$f();}catch(InvalidArgumentException){return;}throw new LogicException('Expected denial');}
$db=new PDO(getenv('MOBA_TEST_DSN'),getenv('MOBA_TEST_USER'),getenv('MOBA_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$u=new Moba\Users($db,['admin_username'=>'root-admin','admin_password_hash'=>password_hash('root-test-password',PASSWORD_DEFAULT)]);
$root=$u->root();$r=new Moba\Records($db);$w=new Moba\WorkOrders($r,$u);
$worker=$u->save(['username'=>'worker-test','name'=>'Worker','role'=>'production','active'=>true,'password'=>'unique-test-password'],$root);
$other=$u->save(['username'=>'other-test','name'=>'Other','role'=>'production','active'=>true,'password'=>'other-test-password'],$root);
check(!isset($worker['password_hash']),'No hash disclosure');
check($u->login('worker-test','wrong')===null,'Wrong password');
$identity=$u->login('worker-test','unique-test-password');check($identity!==null,'Worker login');
denied(fn()=>$u->save(['username'=>'bad'],$worker));
denied(fn()=>$w->save(['name'=>'Forbidden'],$worker));
$order=$w->save(['name'=>'Print shirts','instructions'=>'30 red XL, chest logo','due_date'=>'2026-10-05','assignee_id'=>$worker['id']],$root);
check(count($w->all($worker))===1 && count($w->all($other))===0,'Assignment isolation');
denied(fn()=>$w->get($order['id'],$other));
denied(fn()=>$w->progress(['id'=>$order['id'],'version'=>1,'status'=>'completed'],$other));
$updated=$w->progress(['id'=>$order['id'],'version'=>1,'status'=>'in_progress','progress'=>'Cutting'],$worker);
check($updated['status']==='in_progress' && count($r->history('work_order',$order['id']))===2,'Progress history');
try{$w->progress(['id'=>$order['id'],'version'=>1,'status'=>'completed'],$worker);throw new LogicException('Lost update');}catch(RuntimeException $e){check($e->getCode()===409,'Version conflict');}
$session=['authenticated'=>true,'user_id'=>$worker['id'],'user_version'=>$identity['session_version']];
check($u->current($session)!==null,'Active session');
$worker['password']='replacement-password';$changed=$u->save($worker,$root);
check($u->current($session)===null,'Password update revokes session');
check($u->login('worker-test','unique-test-password')===null,'Old password invalid');
$changed['active']=false;$u->save($changed,$root);
check($u->login('worker-test','replacement-password')===null,'Disabled login');
echo "Users and work orders: roles, assignment, history, revocation OK\n";
