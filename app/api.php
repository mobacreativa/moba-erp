<?php
declare(strict_types=1);
require_once __DIR__.'/Pricing.php';
require_once __DIR__.'/Records.php';
require_once __DIR__.'/Quotes.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['authenticated'])) { http_response_code(401); throw new InvalidArgumentException('Inicia sesión para continuar.'); }
    $records = new \Moba\Records($db);
    $quotes = new \Moba\Quotes($db,$records);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = field($_GET,'api');
        if ($action === 'load') {
            $result = ['csrf'=>$_SESSION['csrf'],'customers'=>$db->query('SELECT * FROM customers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)];
            foreach (\Moba\Records::KINDS as $kind) { $result[$kind] = $records->all($kind); }
        } elseif ($action === 'history') { $result = $records->history(field($_GET,'kind'),field($_GET,'id')); }
        else { throw new InvalidArgumentException('Acción no válida.'); }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'],field($_POST,'csrf'))) { http_response_code(403); throw new InvalidArgumentException('Formulario caducado. Recarga la página.'); }
        if (strlen(field($_POST,'payload')) > 4000000) { throw new InvalidArgumentException('Petición demasiado grande.'); }
        $input = json_decode(field($_POST,'payload'),true,64,JSON_THROW_ON_ERROR);
        if (!is_array($input)) { throw new InvalidArgumentException('Datos no válidos.'); }
        $id = is_string($input['id'] ?? null) ? $input['id'] : '';
        $version = (int)($input['version'] ?? 0);
        $action = field($_GET,'api');
        $result = match ($action) {
            'calculate' => $quotes->calculate($input),
            'save_quote' => $quotes->save($input,$id,$version),
            'status' => $quotes->status($id,$version,(string)($input['status'] ?? '')),
            'save' => (function() use ($records,$input,$id,$version) {
                $kind = (string)($input['kind'] ?? '');
                if ($kind === 'quote') { throw new InvalidArgumentException('Utiliza Guardar presupuesto.'); }
                return $records->save($kind,$input['data'] ?? [],$id,$version);
            })(),
            default => throw new InvalidArgumentException('Acción no válida.'),
        };
    } else { http_response_code(405); throw new InvalidArgumentException('Método no permitido.'); }
    echo json_encode(['ok'=>true,'data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException|\JsonException $e) {
    if (http_response_code() < 400) { http_response_code(422); }
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    $conflict = $e->getCode() === 409;
    http_response_code($conflict ? 409 : 503);
    if (!$conflict) { error_log('MOBA ERP API '.get_class($e).' code '.$e->getCode()); }
    echo json_encode(['ok'=>false,'error'=>$conflict ? $e->getMessage() : 'No se pueden cargar los datos. Comprueba la migración de la base de datos.'],JSON_UNESCAPED_UNICODE);
}
exit;
