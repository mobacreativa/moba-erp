<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Pricing.php';
use Moba\Pricing;
function verify(bool $ok,string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function rejects(callable $fn): void { try { $fn(); } catch (InvalidArgumentException) { return; } throw new RuntimeException('Expected validation error'); }
verify(Pricing::sale(6,40,'margin') === 10.0,'Margin on sale');
verify(abs(Pricing::sale(6,40,'markup')-8.4)<0.0001,'Markup on cost');
rejects(fn()=>Pricing::sale(6,100,'margin'));
rejects(fn()=>Pricing::quantity(1.5));
rejects(fn()=>Pricing::number(INF,'Infinite'));
$p=['name'=>'Camiseta','cost'=>2,'pvp'=>null,'min_qty'=>1,'margin'=>40,'vat'=>21,'techniques'=>['DTF']];
$rate=['active'=>true,'technique'=>'DTF','zone'=>'Pecho','size'=>'8x8','min_qty'=>1,'max_qty'=>24,'basis'=>'cost','unit'=>'unit','amount'=>1.2];
$rate2=$rate;$rate2['min_qty']=25;$rate2['max_qty']=null;$rate2['amount']=1;
$input=['quantity'=>30,'handling'=>0.5,'design'=>15,'personalizations'=>[['technique'=>'DTF','zone'=>'Pecho','size'=>'8x8']]];
$l=Pricing::line($p,$input,[$rate,$rate2]);
verify($l['cost']===120.0,'Garment + DTF + handling + design');
verify($l['price']===6.67 && $l['base']===200.1 && $l['tax']===42.02,'Unit rounding and VAT');
verify($l['description']==='Camiseta con DTF Pecho' && !str_contains($l['description'],'8x8'),'Clean commercial description');
rejects(fn()=>Pricing::line($p,$input,[]));
rejects(fn()=>Pricing::line($p,$input,[$rate2,$rate2]));
$unknown=$p;$unknown['cost']=null;$unknown['pvp']=7;
$u=Pricing::line($unknown,['quantity'=>3],[]);
verify($u['cost']===null && $u['profit']===null && $u['real_margin']===null,'Unknown cost is not zero');
$saleRate=$rate2;$saleRate['basis']='sale';
$u=Pricing::line($p,$input,[$saleRate]);
verify($u['cost']===null && $u['profit']===null && $u['price']===6.0,'Sales tariff added without being misrepresented as cost');
$area=$p;$area['calculation']='area';$area['cost']=5;$area['margin']=0;
$l=Pricing::line($area,['quantity'=>2,'width_cm'=>200,'height_cm'=>100],[]);
verify($l['cost']===20.0 && $l['base']===20.0,'Square metres conversion');
$l=Pricing::line($p,['quantity'=>3,'price'=>7,'discount'=>10],[]);
verify($l['base']===18.9 && $l['tax']===3.97 && $l['total']===22.87,'Discount before VAT');
echo "Pricing: OK\n";
