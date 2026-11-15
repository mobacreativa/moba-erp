<?php
declare(strict_types=1);
namespace Moba;
use InvalidArgumentException;
final class Accounting {
    public function __construct(private Records $records) {}
    public static function date(mixed $value,bool $optional=false): string {
        if ($optional&&$value==='') return '';
        if (!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)||!checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4))) throw new InvalidArgumentException('Fecha no válida.');
        return $value;
    }
    public static function cents(mixed $v): int {
        if ((!is_string($v)&&!is_int($v)&&!is_float($v))||!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',(string)$v)) throw new InvalidArgumentException('Importe no válido: usa hasta dos decimales y valores positivos.');
        $parts=explode('.',(string)$v);return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
    }
    public function save(array $p,array $actor): array {
        Users::allow($actor,['admin']);
        $id=is_string($p['id']??null)?$p['id']:'';
        $old=$id!==''?$this->records->get('account_entry',$id):null;
        $type=$p['type']??'';$name=$p['name']??'';$party=$p['party']??'';$reference=$p['reference']??'';
        if (!in_array($type,['income','expense'],true)||!is_string($name)||trim($name)===''||mb_strlen($name)>190||!is_string($party)||trim($party)===''||mb_strlen($party)>190||!is_string($reference)||mb_strlen($reference)>100) throw new InvalidArgumentException('Indica concepto, cliente/proveedor y tipo válidos.');
        $date=self::date($p['date']??'');$due=self::date($p['due_date']??'',true);
        $base=self::cents($p['base']??'');$tax=self::cents($p['tax']??'');
        $paid=($p['paid']??false)===true;$paidDate=$paid?self::date($p['paid_date']??''):'';
        $invoiceId=$old['invoice_id']??($p['invoice_id']??'');
        if (!is_string($invoiceId)) throw new InvalidArgumentException('Factura no válida.');
        if ($invoiceId!=='') { $invoice=$this->records->get('invoice_upload',$invoiceId);if ($invoice['status']!=='succeeded') throw new InvalidArgumentException('Revisa primero la extracción.'); }
        $doc=['invoice_id'=>$invoiceId,'name'=>trim($name),'type'=>$type,'party'=>trim($party),'reference'=>trim($reference),'date'=>$date,'due_date'=>$due,'base_cents'=>$base,'tax_cents'=>$tax,'total_cents'=>$base+$tax,'currency'=>'EUR','paid'=>$paid,'paid_date'=>$paidDate,'actor'=>$actor['username']];
        return $this->records->save('account_entry',$doc,$id,(int)($p['version']??0));
    }
}
