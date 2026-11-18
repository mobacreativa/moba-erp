<?php
declare(strict_types=1);
namespace Moba;
use InvalidArgumentException;
final class Tasks {
    public function __construct(private Records $records,private Users $users,private string $directory) {}
    public function all(array $a): array { return array_values(array_filter($this->records->all('task'),fn($t)=>$a['role']==='admin'||(int)$t['assignee_id']===(int)$a['id']||(int)$t['creator_id']===(int)$a['id'])); }
    public function get(string $id,array $a): array {
        $t=$this->records->get('task',$id);
        if ($a['role']!=='admin'&&(int)$t['assignee_id']!==(int)$a['id']&&(int)$t['creator_id']!==(int)$a['id']) { http_response_code(403);throw new InvalidArgumentException('No tienes acceso a esta tarea.'); } return $t;
    }
    public function save(array $p,array $a): array {
        $id=is_string($p['id']??null)?$p['id']:'';$old=$id!==''?$this->get($id,$a):null;$state=$p['status']??'pending';
        if (!in_array($state,['pending','in_progress','completed'],true)) throw new InvalidArgumentException('Estado no válido.');
        if ($old&&$a['role']!=='admin'&&(int)$old['creator_id']!==(int)$a['id']) { $doc=$old;$doc['status']=$state; }
        else {
            $name=$p['name']??'';$description=$p['description']??'';
            if (!is_string($name)||!is_string($description)||trim($name)===''||mb_strlen($name)>190||mb_strlen($description)>20000) throw new InvalidArgumentException('Indica un título y descripción válidos.');
            $assignee=filter_var($p['assignee_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if ($assignee===false) throw new InvalidArgumentException('Selecciona un responsable.');
            $u=$assignee===0?$this->users->root():$this->users->get($assignee);
            if (!$u['active']) throw new InvalidArgumentException('El responsable no está activo.');
            $doc=['name'=>trim($name),'description'=>trim($description),'assignee_id'=>$assignee,'assignee_name'=>$u['name'],'creator_id'=>$old['creator_id']??(int)$a['id'],'status'=>$state,'attachments'=>$old['attachments']??[]];
        }
        return $this->records->save('task',$doc,$id,(int)($p['version']??0));
    }
    public function attach(array $p,array $f,array $a): array {
        $t=$this->get((string)($p['id']??''),$a);
        if (count($t['attachments']??[])>=20) throw new InvalidArgumentException('Máximo 20 adjuntos por tarea.');
        if (($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name'])) throw new InvalidArgumentException('No se recibió el archivo. Máximo 5 MB.');
        $size=filesize($f['tmp_name']);if (!$size||$size>5*1024*1024) throw new InvalidArgumentException('El archivo debe ocupar entre 1 byte y 5 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf','text/plain','text/csv','application/msword','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],true)) throw new InvalidArgumentException('Formato no permitido. Usa JPG, PNG, WebP, PDF, TXT, CSV, Word, Excel o ZIP.');
        if (!is_dir($this->directory)&&!mkdir($this->directory,0700,true)&&!is_dir($this->directory)) throw new \RuntimeException('No se puede preparar el almacenamiento.');
        $key=bin2hex(random_bytes(24));$path=$this->directory.'/'.$key;
        if (!move_uploaded_file($f['tmp_name'],$path)) throw new \RuntimeException('No se pudo guardar el archivo.');
        chmod($path,0600);
        $name=preg_replace('/[\x00-\x1F\x7F]/u','',basename(str_replace('\\','/',(string)($f['name']??'archivo'))));
        $t['attachments'][]=['id'=>$key,'name'=>mb_substr($name?:'archivo',0,190),'mime'=>$mime,'size'=>$size];
        try { return $this->records->save('task',$t,$t['id'],(int)($p['version']??0)); } catch (\Throwable $e) { unlink($path);throw $e; }
    }
    public function download(string $id,string $key,array $a): never {
        $t=$this->get($id,$a);
        foreach ($t['attachments']??[] as $f) {
            if ($f['id']!==$key||!preg_match('/^[a-f0-9]{48}$/D',$key)) continue;
            $path=$this->directory.'/'.$key;if (!is_file($path)) break;
            header('Content-Type: '.$f['mime']);header('Content-Length: '.filesize($path));
            header('Content-Disposition: attachment; filename="archivo"; filename*=UTF-8\'\''.rawurlencode($f['name']));
            header("Content-Security-Policy: sandbox; default-src 'none'");readfile($path);exit;
        }
        http_response_code(404);throw new InvalidArgumentException('Adjunto no encontrado.');
    }
}
