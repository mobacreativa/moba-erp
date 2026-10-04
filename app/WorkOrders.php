<?php
declare(strict_types=1);
namespace Moba;
use InvalidArgumentException;
final class WorkOrders
{
    public function __construct(private Records $records,private Users $users) {}
    public function all(array $actor): array {
        return array_values(array_filter($this->records->all('work_order'),fn($o)=>$actor['role']!=='production' || (int)$o['assignee_id']===(int)$actor['id']));
    }
    public function get(string $id,array $actor): array {
        $o=$this->records->get('work_order',$id);
        if ($actor['role']==='production' && (int)$o['assignee_id']!==(int)$actor['id']) { http_response_code(403);throw new InvalidArgumentException('No tienes acceso a esta orden.'); }
        return $o;
    }
    public function save(array $input,array $actor): array {
        Users::allow($actor,['admin','sales']);
        $id=is_string($input['id'] ?? null)?$input['id']:'';
        $old=$id!==''?$this->get($id,$actor):null;
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
        $doc=['name'=>$name,'instructions'=>$instructions,'due_date'=>$date,'assignee_id'=>$assignee,'assignee_name'=>$user['name'],'quote_id'=>$quoteId,'quote_name'=>$quoteName,'status'=>$old['status'] ?? 'pending','progress'=>$old['progress'] ?? '', 'actor'=>$actor['username']];
        return $this->records->save('work_order',$doc,$id,(int)($input['version'] ?? 0));
    }
    public function progress(array $input,array $actor): array {
        Users::allow($actor,['admin','production']);
        $o=$this->get((string)($input['id'] ?? ''),$actor);
        $status=$input['status'] ?? '';$progress=$input['progress'] ?? '';
        if (!is_string($progress) || mb_strlen($progress)>20000 || !in_array($status,['pending','in_progress','blocked','completed'],true)) { throw new InvalidArgumentException('Estado o nota no válida.'); }
        $o['status']=$status;$o['progress']=$progress;$o['actor']=$actor['username'];
        return $this->records->save('work_order',$o,$o['id'],(int)($input['version'] ?? 0));
    }
}
