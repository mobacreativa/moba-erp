<?php
declare(strict_types=1);
require __DIR__.'/../app/Accounting.php';
require __DIR__.'/../app/InvoiceReader.php';
require __DIR__.'/../app/InvoiceSettings.php';
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

$trash=['id'=>$updated['id'],'version'=>$updated['version']];
reject(fn()=>$a->trash($trash,['role'=>'sales']));
$deleted=$a->trash($trash,$admin);check($deleted['deleted']&&$deleted['total_cents']===12112,'Trash preserves amount');
reject(fn()=>$a->save($p,$admin));
try{$a->trash($trash,$admin,true);throw new LogicException('Accepted stale restore');}catch(RuntimeException $e){check($e->getCode()===409,'Restore conflict');}
$restored=$a->trash(['id'=>$deleted['id'],'version'=>$deleted['version']],$admin,true);
check(!$restored['deleted']&&$restored['paid']&&$restored['total_cents']===12112,'Restore payment and amounts');
check(count($r->history('account_entry',$saved['id']))===4,'Trash and restore audit');
$azure=Moba\InvoiceReader::fields(['analyzeResult'=>['documents'=>[['fields'=>['VendorName'=>['valueString'=>'Proveedor Azure SL'],'DueDate'=>['valueDate'=>'2026-12-31'],'InvoiceDate'=>['valueDate'=>'2026-11-17'],'TotalTax'=>['valueCurrency'=>['amount'=>21,'currencyCode'=>'EUR']]]]]]]);
check($azure['fields']['party']==='Proveedor Azure SL'&&$azure['fields']['due_date']==='2026-12-31'&&$azure['fields']['tax']==='21.00','Azure vendor and due date mapping');
echo "Trash, restore, audit, conflicts and Azure invoice fields OK\n";

$settingsFile=tempnam(sys_get_temp_dir(),'moba-test-');
try {
    $settings=['endpoint'=>'https://test.cognitiveservices.azure.com/','key'=>str_repeat('a',32),'tier'=>'F0'];
    reject(fn()=>Moba\InvoiceSettings::save($settings,['role'=>'sales'],$settingsFile));
    $reply=Moba\InvoiceSettings::save($settings,$admin,$settingsFile);
    check($reply===['configured'=>true],'No key in response');
    $private=require $settingsFile;
    check($private['key']===$settings['key']&&$private['enabled']&&$private['tier']==='F0','Private configuration persisted');
    check((fileperms($settingsFile)&0777)===0600,'Private file permissions');
} finally {unlink($settingsFile);}

check((new Moba\InvoiceReader($r,[],"/tmp/unused"))->limits()===["mb"=>4,"pages"=>2],"F0 limits by default");
check((new Moba\InvoiceReader($r,["tier"=>"S0"],"/tmp/unused"))->limits()===["mb"=>5,"pages"=>null],"S0 application limit");
