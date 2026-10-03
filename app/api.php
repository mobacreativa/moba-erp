<?php
declare(strict_types=1);
require_once __DIR__.'/Pricing.php';
require_once __DIR__.'/Records.php';
require_once __DIR__.'/Quotes.php';
require_once __DIR__.'/WorkOrders.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['authenticated'])) { http_response_code(401); throw new InvalidArgumentException('Inicia sesión para continuar.'); }
    $records = new \Moba\Records($db);
    $quotes = new \Moba\Quotes($db,$records);
    $orders = new \Moba\WorkOrders($records,$users);
    $action = field($_GET,'api');
    if (!str_starts_with($action,'team_')) { \Moba\Users::allow($actor,['admin','sales']); }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = field($_GET,'api');
        if ($action === 'team_load') {
            $result=['csrf'=>$_SESSION['csrf'],'actor'=>['id'=>$actor['id'],'name'=>$actor['name'],'role'=>$actor['role']],'orders'=>$orders->all($actor),'users'=>[],'quotes'=>[]];
            if ($actor['role']!=='production') {
                $result['users']=array_map(fn($u)=>$actor['role']==='admin'?$u:array_intersect_key($u,array_flip(['id','name','role','active'])),$users->all());
                $result['quotes']=array_map(fn($q)=>['id'=>$q['id'],'name'=>$q['name']],$records->all('quote'));
            }
        } elseif ($action === 'team_history') {
            $orders->get(field($_GET,'id'),$actor);
            $result=$records->history('work_order',field($_GET,'id'));
            if ($actor['role']==='production') { $result=array_values(array_filter($result,fn($o)=>(int)$o['assignee_id']===(int)$actor['id'])); }
        } elseif ($action === 'load') {
            $result = ['csrf'=>$_SESSION['csrf'],'customers'=>$db->query('SELECT * FROM customers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)];
            foreach (\Moba\Records::KINDS as $kind) { if ($kind!=='work_order') { $result[$kind] = $records->all($kind); } }
        } elseif ($action === 'history') { if (field($_GET,'kind')==='work_order') { throw new InvalidArgumentException('Utiliza el histórico de órdenes.'); } $result = $records->history(field($_GET,'kind'),field($_GET,'id')); }
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
            'team_user' => $users->save($input,$actor),
            'team_order' => $orders->save($input,$actor),
            'team_progress' => $orders->progress($input,$actor),
            'calculate' => $quotes->calculate($input),
            'save_quote' => $quotes->save($input,$id,$version),
            'status' => $quotes->status($id,$version,(string)($input['status'] ?? '')),
            'save' => (function() use ($records,$input,$id,$version,$actor) {
                $kind = (string)($input['kind'] ?? '');
                \Moba\Users::allow($actor,['admin']);
                if (in_array($kind,['quote','work_order'],true)) { throw new InvalidArgumentException('Utiliza Guardar presupuesto.'); }
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
