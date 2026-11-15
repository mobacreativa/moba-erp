<?php
declare(strict_types=1);
require __DIR__.'/../app/Accounting.php';
require __DIR__.'/../app/InvoiceReader.php';
require __DIR__.'/../app/Records.php';
require __DIR__.'/../app/Users.php';
function check(bool $ok,string $label):void {if(!$ok)throw new LogicException($label);}
function reject(callable $f):void {try{$f();}catch(InvalidArgumentException){return;}throw new LogicException('Expected rejection');}
check(Moba\Accounting::cents('123.45')===12345,'Exact cents');
check(Moba\Accounting::cents('0.1')===10,'One decimal');
foreach(['-1','1.234','1e3','',true,[]] as $v)reject(fn()=>Moba\Accounting::cents($v));
reject(fn()=>Moba\Accounting::date('2026-02-29'));
check(Moba\Accounting::date('2028-02-29')==='2028-02-29','Leap year');
$extracted=Moba\InvoiceReader::fields(['analyzeResult'=>['documents'=>[['fields'=>['VendorName'=>['valueString'=>'Test supplier'],'SubTotal'=>['valueCurrency'=>['amount'=>10,'currencyCode'=>'EUR']]]]]]]);
check($extracted['fields']['base']==='10.00'&&$extracted['fields']['tax']===''&&$extracted['fields']['due_date']==='','Missing fields stay unknown');
reject(fn()=>Moba\InvoiceReader::fields(['analyzeResult'=>['documents'=>[]]]));
$db=new PDO(getenv('MOBA_TEST_DSN'),getenv('MOBA_TEST_USER'),getenv('MOBA_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$r=new Moba\Records($db);$a=new Moba\Accounting($r);$admin=['role'=>'admin','username'=>'test'];
$p=['name'=>'Invoice test','party'=>'Supplier','type'=>'expense','date'=>'2026-11-15','due_date'=>'2026-12-01','base'=>'100.10','tax'=>'21.02'];
reject(fn()=>$a->save($p,['role'=>'sales']));
$saved=$a->save($p,$admin);check($saved['total_cents']===12112,'Exact total');
$p['id']=$saved['id'];$p['version']=$saved['version'];$p['paid']=true;
reject(fn()=>$a->save($p,$admin));
$p['paid_date']='2026-11-16';$updated=$a->save($p,$admin);
check($updated['paid']&&$updated['version']===2,'Persist payment');
try{$a->save($p,$admin);throw new LogicException('Accepted stale entry');}catch(RuntimeException $e){check($e->getCode()===409,'Conflict');}
check(count($r->history('account_entry',$saved['id']))===2,'Financial edit history');
echo "Accounting amounts, dates, permissions, persistence, concurrency and OCR mapping OK\n";
