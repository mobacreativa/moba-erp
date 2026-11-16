<?php
declare(strict_types=1);
namespace Moba;
use InvalidArgumentException;
final class InvoiceReader {
    public function __construct(private Records $records,private array $config,private string $directory) {}
    public function enabled(): bool { return ($this->config['enabled']??false)===true&&!empty($this->config['key'])&&!empty($this->config['endpoint']); }
    private function endpoint(): string {
        $url=rtrim($this->config['endpoint']??'','/');
        if (!preg_match('~^https://[a-zA-Z0-9-]+\.cognitiveservices\.azure\.com$~D',$url)) throw new InvalidArgumentException('Configura un endpoint válido de Azure Document Intelligence.');
        return $url;
    }
    private function request(string $url,?string $body=null): array {
        $base=$this->endpoint();if (!str_starts_with($url,$base.'/documentintelligence/')) throw new InvalidArgumentException('Destino de extracción no válido.');
        $headers=[];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Ocp-Apim-Subscription-Key: '.$this->config['key'],'Content-Type: application/octet-stream'],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$p=explode(':',$line,2);if(count($p)===2)$headers[strtolower(trim($p[0]))]=trim($p[1]);return strlen($line);}]);
        if ($body!==null) { curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$body); }
        $result=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if ($result===false||!in_array($code,[200,202],true)) throw new InvalidArgumentException('El servicio de lectura no respondió correctamente. Inténtalo más tarde.');
        return [$headers,json_decode($result,true)??[]];
    }
    public function upload(array $f,array $actor,?array $local=null): array {
        Users::allow($actor,['admin']);
        if ($local===null&&!$this->enabled()) throw new InvalidArgumentException('La lectura automática aún no está conectada.');
        if ($local!==null) {
            $clean=[];foreach (['party','date','due_date','reference','base','tax','invoice_total','currency'] as $field) {
                $value=$local[$field]??'';if (!is_string($value)||mb_strlen($value)>190) throw new InvalidArgumentException('Datos de lectura no válidos.');$clean[$field]=$value;
            }
        }
        if (($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name'])) throw new InvalidArgumentException('No se ha recibido la factura.');
        $size=filesize($f['tmp_name']);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!$size||$size>5*1024*1024||!in_array($mime,['application/pdf','image/jpeg','image/png'],true)) throw new InvalidArgumentException('Sube un PDF, JPG o PNG de hasta 5 MB.');
        if (!is_dir($this->directory)&&!mkdir($this->directory,0700,true)&&!is_dir($this->directory)) throw new \RuntimeException('Almacenamiento no disponible.');
        $key=bin2hex(random_bytes(24));$path=$this->directory.'/'.$key;
        if (!move_uploaded_file($f['tmp_name'],$path)) throw new \RuntimeException('No se pudo guardar la factura.');chmod($path,0600);
        try {
            if ($local!==null) {
                $doc=$this->records->save('invoice_upload',['name'=>mb_substr(basename((string)$f['name']),0,190),'file'=>$key,'mime'=>$mime,'source'=>'browser','status'=>'succeeded','result'=>['fields'=>$clean,'confidence'=>[]]]);
                return ['id'=>$doc['id'],'status'=>'succeeded'];
            }
            [$headers]=$this->request($this->endpoint().'/documentintelligence/documentModels/prebuilt-invoice:analyze?api-version=2024-11-30',file_get_contents($path));
            $operation=$headers['operation-location']??'';if (!str_starts_with($operation,$this->endpoint().'/documentintelligence/')) throw new \RuntimeException('Respuesta de extracción no válida.');
            $doc=$this->records->save('invoice_upload',['name'=>mb_substr(basename((string)$f['name']),0,190),'file'=>$key,'mime'=>$mime,'operation'=>$operation,'status'=>'running']);
            return ['id'=>$doc['id'],'status'=>'running'];
        } catch (\Throwable $e) { unlink($path);throw $e; }
    }
    public static function fields(array $result): array {
        $docs=$result['analyzeResult']['documents']??[];
        if (count($docs)!==1) throw new InvalidArgumentException('Sube una sola factura por archivo.');
        $f=$docs[0]['fields']??[];$data=[];$confidence=[];
        foreach (['date'=>['InvoiceDate','valueDate'],'party'=>['VendorName','valueString'],'reference'=>['InvoiceId','valueString'],'due_date'=>['DueDate','valueDate']] as $key=>[$source,$value]) { $data[$key]=$f[$source][$value]??'';$confidence[$key]=$f[$source]['confidence']??null; }
        foreach (['base'=>'SubTotal','tax'=>'TotalTax','invoice_total'=>'InvoiceTotal'] as $key=>$source) { $value=$f[$source]['valueCurrency']??[];$data[$key]=isset($value['amount'])?number_format((float)$value['amount'],2,'.',''):'';$data['currency']=$value['currencyCode']??($data['currency']??'');$confidence[$key]=$f[$source]['confidence']??null; }
        return ['fields'=>$data,'confidence'=>$confidence];
    }
    public function poll(string $id,array $actor): array {
        Users::allow($actor,['admin']);$doc=$this->records->get('invoice_upload',$id);
        if ($doc['status']==='succeeded') return ['id'=>$id,'status'=>'succeeded']+$doc['result'];
        if (!$this->enabled()) throw new InvalidArgumentException('El lector no está configurado.');
        [, $result]=$this->request($doc['operation']);
        if (($result['status']??'')==='failed') throw new InvalidArgumentException('No se pudo leer la factura.');
        if (($result['status']??'')!=='succeeded') return ['id'=>$id,'status'=>'running'];
        $doc['result']=self::fields($result);$doc['status']='succeeded';$this->records->save('invoice_upload',$doc,$id,$doc['version']);
        return ['id'=>$id,'status'=>'succeeded']+$doc['result'];
    }
    public function download(string $id,array $actor): never {
        Users::allow($actor,['admin']);$doc=$this->records->get('invoice_upload',$id);$key=$doc['file'];
        if (!preg_match('/^[a-f0-9]{48}$/D',$key)||!is_file($this->directory.'/'.$key)) throw new InvalidArgumentException('Factura no disponible.');
        header('Content-Type: '.$doc['mime']);header('Content-Disposition: attachment; filename="factura"; filename*=UTF-8\'\''.rawurlencode($doc['name']));header("Content-Security-Policy: sandbox; default-src 'none'");readfile($this->directory.'/'.$key);exit;
    }
}
