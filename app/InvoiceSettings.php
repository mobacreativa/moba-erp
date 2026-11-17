<?php
declare(strict_types=1);
namespace Moba;
use InvalidArgumentException;
final class InvoiceSettings {
    public static function save(array $p,array $actor,string $path): array {
        Users::allow($actor,['admin']);
        $endpoint=is_string($p['endpoint']??null)?rtrim(trim($p['endpoint']),'/'):'';
        $key=is_string($p['key']??null)?trim($p['key']):'';
        if (!preg_match('~^https://[a-zA-Z0-9-]+\.cognitiveservices\.azure\.com$~D',$endpoint)) throw new InvalidArgumentException('Endpoint de Azure no válido.');
        if (!preg_match('/^[a-zA-Z0-9]{32,128}$/D',$key)) throw new InvalidArgumentException('Introduce una clave válida del recurso Azure.');
        if (!in_array($p['tier']??'', ['F0','S0'],true)) throw new InvalidArgumentException('Plan de Azure no válido.');
        $config=['enabled'=>true,'endpoint'=>$endpoint,'key'=>$key,'tier'=>$p['tier']];
        $temp=tempnam(dirname($path),'.ocr-');
        if ($temp===false) throw new \RuntimeException('No se pudo preparar la configuración privada.');
        try {
            if (!chmod($temp,0600)||file_put_contents($temp,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX)===false||!rename($temp,$path)) throw new \RuntimeException('No se pudo guardar la configuración privada.');
            if (function_exists('opcache_invalidate')) opcache_invalidate($path,true);
        } finally { if (is_file($temp)) unlink($temp); }
        return ['configured'=>true]; // Never return the secret, even masked.
    }
}
