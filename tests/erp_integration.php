<?php
declare(strict_types=1);
require __DIR__.'/pricing.php';
require __DIR__.'/../app/Records.php';
require __DIR__.'/../app/Quotes.php';
$db=new PDO(getenv('MOBA_TEST_DSN'),getenv('MOBA_TEST_USER'),getenv('MOBA_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$r=new Moba\Records($db);$q=new Moba\Quotes($db,$r);
$supplier=$r->save('supplier',['name'=>'Distributor']);
$brand=$r->save('brand',['name'=>'Manufacturer']);
$color=$r->save('color',['name'=>'Negro','code'=>'02','hex'=>'#000000','supplier_id'=>$supplier['id']]);
$size=$r->save('size',['name'=>'M']);
$p=$r->save('product',['name'=>'Integration garment','cost'=>2,'pvp'=>null,'brand_id'=>$brand['id'],'supplier_id'=>$supplier['id'],'color_supplier_id'=>$supplier['id'],'color_ids'=>[$color['id']],'size_ids'=>[$size['id']],'techniques'=>[]]);
try {$r->save('product',$p,$p['id'],0);throw new LogicException('Accepted stale write');}catch(RuntimeException $e){verify($e->getCode()===409,'Optimistic concurrency');}
$db->exec("INSERT INTO customers(name) VALUES ('Integration customer')");$customerId=(int)$db->lastInsertId();
$line=['product_id'=>$p['id'],'quantity'=>3,'distribution'=>[['color_id'=>$color['id'],'size_id'=>$size['id'],'quantity'=>3]],'price'=>7];
$quote=$q->save(['customer_id'=>$customerId,'date'=>'2026-10-02','lines'=>[$line]],'',0);
verify($quote['base']===21.0 && $quote['lines'][0]['input']['distribution'][0]['color']==='02 · Negro','Persist quote and distribution');
$p['cost']=4;$p=$r->save('product',$p,$p['id'],$p['version']);
$saved=$r->get('quote',$quote['id']);verify($saved['lines'][0]['cost']==6,'Changing catalog does not alter saved quote');
$quote=$q->save(['customer_id'=>$customerId,'date'=>'2026-10-02','lines'=>[['saved_index'=>0]],'notes'=>'Updated conditions'],$quote['id'],$quote['version']);
verify($quote['lines'][0]['cost']==6,'Editing notes keeps historical line');
verify(count($r->history('quote',$quote['id']))===2,'Append-only revisions');
$quote=$q->status($quote['id'],$quote['version'],'issued');
rejects(fn()=>$q->save(['customer_id'=>$customerId,'date'=>'2026-10-02','lines'=>[$line]],$quote['id'],$quote['version']));
$quote=$q->status($quote['id'],$quote['version'],'accepted');verify($quote['status']==='accepted','Quote lifecycle');
$bad=$line;$bad['distribution'][0]['color_id']=str_repeat('f',32);rejects(fn()=>$q->calculate($bad));
$next=$q->save(['customer_id'=>$customerId,'date'=>'2026-10-02','lines'=>[$line]],'',0);verify($next['name']!==$quote['name'],'Unique sequence');
echo "ERP persistence, versioning and quote lifecycle: OK\n";
