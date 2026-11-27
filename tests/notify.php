<?php
declare(strict_types=1);
// Avisos por correo al asignar órdenes y tareas. Solo contra la base desechable de CI; no envía correos reales.
require __DIR__.'/../app/Auth.php';
require __DIR__.'/../app/Users.php';
require __DIR__.'/../app/Records.php';
require __DIR__.'/../app/Customers.php';
require __DIR__.'/../app/Mailer.php';
require __DIR__.'/../app/Notifier.php';
require __DIR__.'/../app/WorkOrders.php';
require __DIR__.'/../app/GeneralTasks.php';
require __DIR__.'/../app/ShopifyWebhook.php';
function check(bool $ok,string $message):void { if(!$ok)throw new LogicException($message); }
function denied(callable $f,string $needle=''):void {try{$f();}catch(InvalidArgumentException $e){if($needle===''||str_contains($e->getMessage(),$needle))return;throw new LogicException('Unexpected message: '.$e->getMessage());}throw new LogicException('Expected denial');}
$db=new PDO(getenv('MOBA_TEST_DSN'),getenv('MOBA_TEST_USER'),getenv('MOBA_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$config=['admin_username'=>'root-admin','admin_password_hash'=>password_hash('root-test-password',PASSWORD_DEFAULT),'admin_email'=>'root@example.com'];
$u=new Moba\Users($db,$config);$root=$u->root();$r=new Moba\Records($db);
$sent=[];$transport=function(string $to,string $subject,string $body,string $headers,string $params)use(&$sent):bool{$sent[]=compact('to','subject','body','headers','params');return true;};
$notifier=new Moba\Notifier($u,new Moba\Mailer($config,$transport),$config);
$w=new Moba\WorkOrders($r,$u,$db,$notifier);$t=new Moba\Tasks($r,$u,sys_get_temp_dir().'/moba-notify-files',$notifier);
$tag=bin2hex(random_bytes(3));

if (($argv[1] ?? '')==='without-email-column') {
    // Antes de importar 004_user_email.sql el ERP sigue funcionando y explica cómo activar los correos.
    check(!$u->hasEmail(),'Column must not exist yet');
    $plain=$u->save(['username'=>'plain-'.$tag,'name'=>'Plain','role'=>'production','active'=>true,'password'=>'plain-user-password'],$root);
    check(($plain['email']??null)==='' && ($u->all()[0]['email']??null)==='','Email empty without column');
    denied(fn()=>$u->save(['username'=>'mail-'.$tag,'name'=>'Mail','email'=>'a@example.com','role'=>'production','active'=>true,'password'=>'mail-user-password'],$root),'004_user_email.sql');
    $w->save(['name'=>'No column','assignee_id'=>$plain['id']],$root);
    check($sent===[],'No mail without column');
    echo "Notify: works without email column OK\n";exit;
}

check($u->hasEmail(),'Email column present');
denied(fn()=>$u->save(['username'=>'bad-'.$tag,'name'=>'Bad','email'=>'no-es-correo','role'=>'production','active'=>true,'password'=>'bad-user-password'],$root),'correo');
$ana=$u->save(['username'=>'ana-'.$tag,'name'=>'Ana','email'=>"ana-$tag@example.com",'role'=>'production','active'=>true,'password'=>'ana-user-password'],$root);
$bea=$u->save(['username'=>'bea-'.$tag,'name'=>'Bea','email'=>"bea-$tag@example.com",'role'=>'admin','active'=>true,'password'=>'bea-user-password'],$root);
$nomail=$u->save(['username'=>'nomail-'.$tag,'name'=>'Sin correo','role'=>'production','active'=>true,'password'=>'nomail-password'],$root);
check($ana['email']==="ana-$tag@example.com",'Email saved');
$ana['email']="ana2-$tag@example.com";$ana=$u->save($ana,$root);check($ana['email']==="ana2-$tag@example.com",'Email updated');

// Orden nueva: un aviso a la persona asignada, sin precios ni cliente.
$o=$w->save(['name'=>'Camisetas evento','due_date'=>'2026-12-15','assignee_id'=>$ana['id'],'items'=>[['product'=>'Camiseta roja XL','quantity'=>30]]],$root);
check(count($sent)===1 && $sent[0]['to']==="ana2-$tag@example.com",'Order assignment mailed');
check(str_contains(mb_decode_mimeheader($sent[0]['subject']),'Nueva orden asignada: Camisetas evento'),'Subject');
foreach(['Hola Ana','Camisetas evento','15/12/2026','Camiseta roja XL × 30','/?view=team'] as $n)check(str_contains($sent[0]['body'],$n),'Body contains '.$n);
check(str_contains($sent[0]['headers'],'noreply@mobacreativa.com') && $sent[0]['params']==='-fnoreply@mobacreativa.com','Sender noreply');
// Editar sin cambiar responsable: sin aviso. Reasignar: aviso al nuevo. Asignarse a sí mismo: sin aviso.
$o=$w->save(['id'=>$o['id'],'version'=>$o['version'],'name'=>'Camisetas evento v2','assignee_id'=>$ana['id']],$root);check(count($sent)===1,'No mail without reassignment');
$o=$w->save(['id'=>$o['id'],'version'=>$o['version'],'name'=>'Camisetas evento v2','assignee_id'=>$bea['id']],$root);check(count($sent)===2 && $sent[1]['to']==="bea-$tag@example.com",'Reassignment mailed');
$w->save(['name'=>'Para mí','assignee_id'=>$bea['id']],$bea);check(count($sent)===2,'No mail when assigning to yourself');
$w->save(['name'=>'Sin correo','assignee_id'=>$nomail['id']],$root);check(count($sent)===2,'No mail when user has no email');
$w->save(['name'=>'Otra orden','assignee_id'=>$bea['id']],$root);check(count($sent)===3,'Each new assignment mailed');

// Tareas generales: aviso al asignar, también al administrador principal si tiene admin_email.
$task=$t->save(['name'=>'Revisar pedido','description'=>'Detalles','assignee_id'=>$bea['id']],$root);
check(count($sent)===4 && $sent[3]['to']==="bea-$tag@example.com" && str_contains(mb_decode_mimeheader($sent[3]['subject']),'Nueva tarea asignada: Revisar pedido') && str_contains($sent[3]['body'],'/?view=tasks'),'Task mailed');
$t->save(['id'=>$task['id'],'version'=>$task['version'],'name'=>'Revisar pedido','description'=>'Más','assignee_id'=>$bea['id']],$root);check(count($sent)===4,'Task edit without reassignment');
$t->save(['name'=>'Para el principal','assignee_id'=>0],$bea);check(count($sent)===5 && $sent[4]['to']==='root@example.com','Root admin mailed via admin_email');

// Pedidos de Shopify: nunca envían aviso (el webhook no usa notificador).
$secret='notify-secret';$hook=new Moba\ShopifyWebhook($db,$r,$u,new Moba\WorkOrders($r,$u,$db),new Moba\Customers($db),['shopify'=>['webhook_secret'=>$secret,'assignee_username'=>$bea['username']]]);
$body=json_encode(['id'=>random_int(10000000,99999999),'name'=>'#9'.$tag,'line_items'=>[['title'=>'Gorra','quantity'=>1]]]);
$res=$hook->handle($body,['hmac'=>base64_encode(hash_hmac('sha256',$body,$secret,true)),'topic'=>'orders/create','shop'=>'']);
check($res['result']==='Orden creada.' && count($sent)===5,'Shopify orders do not mail');

// Un fallo del correo no impide guardar.
$failing=new Moba\WorkOrders($r,$u,$db,new Moba\Notifier($u,new Moba\Mailer($config,fn()=>throw new RuntimeException('smtp down')),$config));
$ok=$failing->save(['name'=>'Aunque falle el correo','assignee_id'=>$bea['id']],$root);check($ok['name']==='Aunque falle el correo','Save survives mail failure');
// Correo desactivado en configuración: no se envía nada.
$off=new Moba\Mailer(['mail'=>['enabled'=>false]],$transport);check(!$off->send('x@example.com','a','b') && count($sent)===5,'Disabled mailer');
// Cabeceras: el asunto no permite inyectar líneas.
(new Moba\Mailer([], $transport))->send('x@example.com',"Hola\r\nBcc: evil@example.com",'b');check(!str_contains(end($sent)['subject'],"\n") && !str_contains(end($sent)['headers'],'evil'),'No header injection');
echo "Notify: users email, order/task assignment mails, reassignment, self, Shopify, failures OK\n";
