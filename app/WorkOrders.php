<?php
declare(strict_types=1);
namespace Moba;
use InvalidArgumentException;
final class WorkOrders
{
    public function __construct(private Records $records,private Users $users,private ?\PDO $db=null,private ?Notifier $notifier=null) {}
    public function all(array $actor,bool $deleted=false): array {
        if ($deleted && $actor['role']==='production') { return []; }
        return array_values(array_filter($this->records->all('work_order'),fn($o)=>(bool)($o['deleted']??false)===$deleted && ($actor['role']!=='production' || (int)$o['assignee_id']===(int)$actor['id'])));
    }
    public function get(string $id,array $actor): array {
        $o=$this->records->get('work_order',$id);
        if ($actor['role']==='production' && ((int)$o['assignee_id']!==(int)$actor['id'] || ($o['deleted']??false))) { http_response_code(403);throw new InvalidArgumentException('No tienes acceso a esta orden.'); }
        return $o;
    }
    /** Envía la orden a la papelera o la restaura. Conserva el documento y su historial. */
    public function trash(array $input,array $actor,bool $restore=false): array {
        Users::allow($actor,['admin','sales']);
        if (!is_string($input['id'] ?? null) || $input['id']==='') { throw new InvalidArgumentException('Orden no válida.'); }
        $o=$this->get($input['id'],$actor);
        if ((bool)($o['deleted']??false)===!$restore) { throw new InvalidArgumentException($restore?'La orden no está en la papelera.':'La orden ya está en la papelera.'); }
        $o['deleted']=!$restore;$o['deleted_at']=$restore?'':gmdate('c');$o['actor']=$actor['username'];
        return $this->records->save('work_order',$o,$o['id'],(int)($input['version'] ?? 0));
    }
    /** $extra: datos internos de origen (p. ej. pedido de Shopify); solo los fija el servidor y se conservan al editar. */
    public function save(array $input,array $actor,array $extra=[]): array {
        Users::allow($actor,['admin','sales']);
        $id=is_string($input['id'] ?? null)?$input['id']:'';
        $old=$id!==''?$this->get($id,$actor):null;
        if ($old['deleted']??false) { throw new InvalidArgumentException('Restaura la orden desde la papelera antes de editarla.'); }
        foreach (['name','instructions','due_date','quote_id'] as $key) { if (!is_string($input[$key] ?? '')) { throw new InvalidArgumentException('Orden no válida.'); } }
        $name=trim($input['name'] ?? '');$instructions=trim($input['instructions'] ?? '');$date=$input['due_date'] ?? '';
        if ($name==='' || mb_strlen($name)>190 || mb_strlen($instructions)>20000) { throw new InvalidArgumentException('Indica un título y unas instrucciones válidas.'); }
        if ($date!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))) { throw new InvalidArgumentException('Fecha no válida.'); }
        $assignee=filter_var($input['assignee_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($assignee===false) { throw new InvalidArgumentException('Asigna la orden a un usuario.'); }
        $user=$this->users->get($assignee);
        if (!$user['active'] || !in_array($user['role'],['admin','production'],true)) { throw new InvalidArgumentException('El responsable debe ser un usuario activo de producción o administrador.'); }
        $quoteId=$input['quote_id'] ?? '';$quoteName='';
        if ($quoteId!=='') { $quote=$this->records->get('quote',$quoteId);$quoteName=$quote['name']; }
        $customerId=(int)($input['customer_id'] ?? ($old['customer_id'] ?? 0));$customerName='';
        if ($quoteId!=='') { $customerId=(int)$quote['customer_id'];$customerName=(string)$quote['customer']['name']; }
        elseif ($customerId>0) {
            if (!$this->db) { throw new InvalidArgumentException('No se puede consultar el cliente.'); }
            $stmt=$this->db->prepare('SELECT name FROM customers WHERE id=?');$stmt->execute([$customerId]);
            $customerName=$stmt->fetchColumn();if ($customerName===false) { throw new InvalidArgumentException('Cliente no encontrado.'); }
        }
        $payment=$input['payment_method'] ?? ($old['payment_method'] ?? '');
        if (!in_array($payment,['','cash','invoiced'],true)) { throw new InvalidArgumentException('Forma de pago no válida.'); }
        $items=$input['items']??($old['items']??[]);
        if (!is_array($items)||!array_is_list($items)||count($items)>100) throw new InvalidArgumentException('Añade como máximo 100 productos.');
        $clean=[];
        foreach($items as $item){
            if(!is_array($item)||!is_string($item['product']??null)||trim($item['product'])===''||mb_strlen($item['product'])>190) throw new InvalidArgumentException('Indica el producto en cada línea.');
            $quantity=filter_var($item['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
            if($quantity===false) throw new InvalidArgumentException('La cantidad debe ser un número entero mayor que cero.');
            $clean[]=['product'=>trim($item['product']),'quantity'=>$quantity];
        }
        $doc=['items'=>$clean,'customer_id'=>$customerId,'customer_name'=>$customerName,'payment_method'=>$payment,'name'=>$name,'instructions'=>$instructions,'due_date'=>$date,'assignee_id'=>$assignee,'assignee_name'=>$user['name'],'quote_id'=>$quoteId,'quote_name'=>$quoteName,'status'=>$old['status'] ?? 'pending','progress'=>$old['progress'] ?? '', 'actor'=>$actor['username']];
        foreach (['source','shopify_order_id','shopify_order_name'] as $key) {
            if (array_key_exists($key,$extra)) { $doc[$key]=(string)$extra[$key]; }
            elseif (isset($old[$key])) { $doc[$key]=$old[$key]; }
        }
        $saved=$this->records->save('work_order',$doc,$id,(int)($input['version'] ?? 0));
        if ($this->notifier && Notifier::shouldNotify($old,$saved,$actor)) { $this->notifier->order($saved,$actor); }
        return $saved;
    }
    /** Cambia solo la fecha de entrega estimada (vacía = sin fecha). */
    public function due(array $input,array $actor): array {
        Users::allow($actor,['admin','sales']);
        $o=$this->get((string)($input['id'] ?? ''),$actor);
        if ($o['deleted']??false) { throw new InvalidArgumentException('Restaura la orden desde la papelera antes de editarla.'); }
        $date=$input['due_date'] ?? '';
        if (!is_string($date) || ($date!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))))) { throw new InvalidArgumentException('Fecha no válida.'); }
        $o['due_date']=$date;$o['actor']=$actor['username'];
        return $this->records->save('work_order',$o,$o['id'],(int)($input['version'] ?? 0));
    }
    public function progress(array $input,array $actor): array {
        Users::allow($actor,['admin','production']);
        $o=$this->get((string)($input['id'] ?? ''),$actor);
        if ($o['deleted']??false) { throw new InvalidArgumentException('Restaura la orden desde la papelera antes de actualizarla.'); }
        $status=$input['status'] ?? '';$progress=$input['progress'] ?? '';
        if (!is_string($progress) || mb_strlen($progress)>20000 || !in_array($status,['pending','in_progress','blocked','completed'],true)) { throw new InvalidArgumentException('Estado o nota no válida.'); }
        $o['status']=$status;$o['progress']=$progress;$o['actor']=$actor['username'];
        return $this->records->save('work_order',$o,$o['id'],(int)($input['version'] ?? 0));
    }
}
