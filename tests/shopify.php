<?php
declare(strict_types=1);
// Pedidos de Shopify -> órdenes de trabajo. Solo contra la base desechable de CI.
require __DIR__.'/../app/Auth.php';
require __DIR__.'/../app/Users.php';
require __DIR__.'/../app/Records.php';
require __DIR__.'/../app/Customers.php';
require __DIR__.'/../app/WorkOrders.php';
require __DIR__.'/../app/ShopifyWebhook.php';
function check(bool $ok,string $message):void { if(!$ok)throw new LogicException($message); }
$db=new PDO(getenv('MOBA_TEST_DSN'),getenv('MOBA_TEST_USER'),getenv('MOBA_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$u=new Moba\Users($db,['admin_username'=>'root-admin','admin_password_hash'=>password_hash('root-test-password',PASSWORD_DEFAULT)]);
$root=$u->root();$r=new Moba\Records($db);$w=new Moba\WorkOrders($r,$u,$db);$c=new Moba\Customers($db);
$worker=$u->save(['username'=>'shop-worker-'.bin2hex(random_bytes(3)),'name'=>'Shop worker','role'=>'production','active'=>true,'password'=>'shop-worker-password'],$root);
$secret='test-secret-'.bin2hex(random_bytes(4));
$config=['shopify'=>['webhook_secret'=>$secret,'assignee_username'=>$worker['username'],'shop_domain'=>'moba-test.myshopify.com']];
$hook=new Moba\ShopifyWebhook($db,$r,$u,$w,$c,$config);
$email='web-'.bin2hex(random_bytes(3)).'@example.com';
$order=['id'=>random_int(1000000,9999999),'name'=>'#1001','created_at'=>'2026-11-27T10:00:00+01:00','email'=>$email,'financial_status'=>'pending','note'=>'Entregar antes del viernes',
 'customer'=>['first_name'=>'Lucía','last_name'=>'Pérez','email'=>$email],
 'shipping_address'=>['name'=>'Lucía Pérez','address1'=>'Calle Mayor 1','zip'=>'41001','city'=>'Sevilla','province'=>'Sevilla','country'=>'Spain','phone'=>'600000000'],
 'shipping_lines'=>[['title'=>'Envío estándar','price'=>'4.95']],
 'note_attributes'=>[['name'=>'Fecha evento','value'=>'2026-12-05'],['name'=>'_interno','value'=>'oculto']],
 'line_items'=>[['title'=>'Camiseta personalizada','variant_title'=>'Rojo / XL','sku'=>'CAM-R-XL','quantity'=>30,'price'=>'12.00','properties'=>[['name'=>'Texto','value'=>'MOBA <b>2026</b>'],['name'=>'_hidden','value'=>'x']]],['title'=>'Taza','variant_title'=>'','sku'=>'','quantity'=>12,'price'=>'6.00']]];
$body=json_encode($order,JSON_UNESCAPED_UNICODE);$sign=fn(string $b,string $s=null)=>base64_encode(hash_hmac('sha256',$b,$s??$secret,true));
$h=['hmac'=>$sign($body),'topic'=>'orders/create','shop'=>'moba-test.myshopify.com'];
$before=count($w->all($root));$customersBefore=(int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
check($hook->handle($body,['hmac'=>$sign($body,'wrong')]+$h)['status']===401,'Bad signature rejected');
check($hook->handle($body.' ',$h)['status']===401,'Tampered body rejected');
check($hook->handle($body,['shop'=>'other.myshopify.com']+$h)['status']===401,'Other shop rejected');
check((new Moba\ShopifyWebhook($db,$r,$u,$w,$c,[]))->handle($body,$h)['status']===503,'Missing config');
check($hook->handle($body,['topic'=>'orders/paid']+$h)['result']==='Evento ignorado.','Other topics ignored');
check(count($w->all($root))===$before,'Nothing created by rejected calls');
$out=$hook->handle($body,$h);check($out['status']===200 && $out['result']==='Orden creada.','Order created');
$o=$r->get('work_order',$out['order_id']);
check($o['source']==='shopify' && $o['shopify_order_id']===(string)$order['id'] && $o['shopify_order_name']==='#1001','Source fields');
check($o['name']==='Shopify #1001 · Lucía Pérez' && (int)$o['assignee_id']===(int)$worker['id'] && $o['status']==='pending','Order basics');
check($o['items']===[['product'=>'Camiseta personalizada — Rojo / XL (CAM-R-XL)','quantity'=>30],['product'=>'Taza','quantity'=>12]],'Items with variants');
foreach(['Texto: MOBA <b>2026</b>','Entregar antes del viernes','Fecha evento: 2026-12-05','Envío estándar','Calle Mayor 1','pendiente'] as $needle)check(str_contains($o['instructions'],$needle),'Instructions contain '.$needle);
foreach(['_hidden','oculto','12.00','4.95'] as $needle)check(!str_contains($o['instructions'],$needle),'Instructions exclude '.$needle);
$cust=$c->find((int)$o['customer_id']);check($cust!==null && $cust['email']===$email && $cust['name']==='Lucía Pérez' && str_contains($cust['address'],'41001 Sevilla') && $o['customer_name']==='Lucía Pérez','Customer created');
$dup=$hook->handle($body,$h);check($dup['status']===200 && $dup['order_id']===$out['order_id'] && count($w->all($root))===$before+1,'Duplicate ignored');
// Editar la orden en el ERP conserva la fuente, y un reenvío posterior sigue sin duplicar.
$edited=$w->save(['id'=>$o['id'],'version'=>$o['version'],'name'=>'Editada','assignee_id'=>$worker['id'],'customer_id'=>$o['customer_id']],$root);
check($edited['source']==='shopify' && $edited['shopify_order_id']===(string)$order['id'],'Source kept on edit');
check($hook->handle($body,$h)['order_id']===$o['id'],'Still deduplicated after edit');
// Segundo pedido del mismo email: vincula el cliente existente.
$order2=['id'=>$order['id']+1,'name'=>'#1002']+$order;$body2=json_encode($order2);
$out2=$hook->handle($body2,['hmac'=>$sign($body2)]+$h);check($r->get('work_order',$out2['order_id'])['customer_id']===$o['customer_id'],'Existing customer linked');
check((int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()===$customersBefore+1,'Only one customer created');
// Las órdenes manuales no llevan fuente web.
$manual=$w->save(['name'=>'Manual','assignee_id'=>$worker['id']],$root);check(!isset($manual['source']),'Manual order without web source');
// Responsable mal configurado: error claro y sin orden creada.
$bad=new Moba\ShopifyWebhook($db,$r,$u,$w,$c,['shopify'=>['webhook_secret'=>$secret,'assignee_username'=>'nobody']]);
$order3=['id'=>$order['id']+2]+$order;$body3=json_encode($order3);
try{$bad->handle($body3,['hmac'=>$sign($body3)]+$h);throw new LogicException('Expected assignee error');}catch(InvalidArgumentException){}
echo "Shopify webhook: signature, dedupe, source, customer, items, edits and config errors OK\n";
