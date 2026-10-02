<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Customers.php';
require dirname(__DIR__) . '/app/Auth.php';
use Moba\Customers;
use Moba\Auth;

function e(mixed $value): string { return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function field(array $input, string $key): string { return isset($input[$key]) && is_string($input[$key]) ? $input[$key] : ''; }
function redirect(): never { header('Location: /'); exit; }

if (!in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/', '/index.php'], true)) {
    http_response_code(404);
    exit('Página no encontrada.');
}

header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$configFile = dirname(__DIR__) . '/config/local.php';
if (!is_file($configFile)) { http_response_code(503); exit('Configura la aplicación siguiendo README.md.'); }
$config = require $configFile;
if (empty($config['admin_password_hash']) || empty($config['admin_username'])) { http_response_code(503); exit('Falta configurar el acceso del administrador.'); }
if (($config['secure_cookie'] ?? true) && ($_SERVER['HTTPS'] ?? '') !== 'on') {
    http_response_code(426);
    exit('Accede a la aplicación mediante HTTPS.');
}
if ($config['secure_cookie'] ?? true) { header('Strict-Transport-Security: max-age=31536000'); }
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => $config['secure_cookie'] ?? true, 'samesite' => 'Strict']);
session_start();
if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_seen'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
$editing = null;
$rows = [];
$query = substr(field($_GET, 'q'), 0, 160);
try {
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $customers = new Customers($db);
    if (isset($_GET['api'])) { require dirname(__DIR__) . '/app/api.php'; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], field($_POST, 'csrf'))) {
            http_response_code(403);
            throw new InvalidArgumentException('La sesión del formulario ha caducado. Recarga la página.');
        }
        $action = field($_POST, 'action');
        if ($action === 'login') {
            $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            // Contador persistente, independiente de la cookie. Incremento atómico antes de verificar.
            $stmt = $db->prepare('INSERT INTO login_attempts (ip_hash,failures,last_attempt) VALUES (?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE failures=IF(last_attempt < UTC_TIMESTAMP() - INTERVAL 15 MINUTE,1,failures+1), last_attempt=UTC_TIMESTAMP()');
            $stmt->execute([$ip]);
            $stmt = $db->prepare('SELECT failures FROM login_attempts WHERE ip_hash=?');
            $stmt->execute([$ip]);
            if ((int) $stmt->fetchColumn() > 5) {
                http_response_code(429);
                throw new InvalidArgumentException('Demasiados intentos. Espera 15 minutos antes de volver a intentarlo.');
            }
            if (!Auth::verify(field($_POST, 'username'), field($_POST, 'password'), $config)) {
                throw new InvalidArgumentException('Usuario o contraseña incorrectos.');
            }
            $db->prepare('DELETE FROM login_attempts WHERE ip_hash=?')->execute([$ip]);
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            redirect();
        }
        if (empty($_SESSION['authenticated'])) { http_response_code(403); throw new InvalidArgumentException('Inicia sesión para continuar.'); }
        if ($action === 'logout') { $_SESSION = []; session_destroy(); redirect(); }
        if ($action === 'save_customer') {
            $idText = field($_POST, 'id');
            $id = $idText === '' ? null : filter_var($idText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) { throw new InvalidArgumentException('Identificador no válido.'); }
            $editing = $_POST;
            $customers->save($_POST, $id);
            $_SESSION['success'] = $id === null ? 'Cliente creado.' : 'Cliente actualizado.';
            redirect();
        }
    }
    if (!empty($_SESSION['authenticated']) && isset($_GET['edit'])) {
        $id = filter_var(field($_GET, 'edit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $editing = $id === false ? null : $customers->find($id);
        if ($editing === null) { http_response_code(404); $error = 'El cliente no existe.'; }
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('MOBA: ' . get_class($exception) . ' code ' . $exception->getCode());
    http_response_code(503);
    $error = 'No se puede acceder a los datos. Revisa la configuración y la base de datos.';
}
if (!empty($_SESSION['authenticated']) && isset($customers)) {
    try { $rows = $customers->search($query); }
    catch (Throwable $exception) { http_response_code(503); $error = 'No se pueden cargar los clientes.'; }
}
if (!empty($_SESSION['authenticated']) && field($_GET,'view') === 'erp') {
    require dirname(__DIR__) . '/app/erp.php';
    exit;
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Clientes · MOBA ERP</title><link rel="stylesheet" href="/app.css"></head>
<body><header><a class="brand" href="/">MOBA <span>ERP</span></a><span>Gestión del taller</span>
<?php if (!empty($_SESSION['authenticated'])): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="secondary">Cerrar sesión</button></form><?php endif ?>
</header><main>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if ($success): ?><p class="success" role="status"><?= e($success) ?></p><?php endif ?>
<?php if (empty($_SESSION['authenticated'])): ?>
<section class="login"><p class="eyebrow">MOBA CREATIVA</p><h1>Tu taller, organizado.</h1><p>Acceso privado a la gestión de clientes.</p><form method="post"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><label>Usuario<input name="username" required autocomplete="username" maxlength="80"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar</button></form></section>
<?php else: ?>
<p><a href="/?view=erp">Abrir presupuestos y catálogo →</a></p><p class="eyebrow">AGENDA COMERCIAL</p><h1>Clientes</h1><p>Los contactos que dan vida a tus proyectos.</p>
<div class="layout"><section><form class="search" method="get"><label>Buscar por nombre o NIF<input name="q" value="<?= e($query) ?>" maxlength="160" placeholder="Nombre o NIF"></label><button>Buscar</button></form>
<div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Contacto</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small><?= e($row['tax_id']) ?></small></td><td><?= e($row['email']) ?><small><?= e($row['phone']) ?></small></td><td><a href="/?edit=<?= e($row['id']) ?>">Editar<span class="sr-only"> <?= e($row['name']) ?></span></a></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="3">No hay clientes<?= $query !== '' ? ' que coincidan con la búsqueda' : ' todavía' ?>.</td></tr><?php endif ?>
</tbody></table></div><small>Se muestran hasta 100 resultados. Usa la búsqueda para acotar.</small></section>
<section class="card"><h2><?= !empty($editing['id']) ? 'Editar cliente' : 'Nuevo cliente' ?></h2><form method="post"><input type="hidden" name="action" value="save_customer"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= e(is_scalar($editing['id'] ?? '') ? ($editing['id'] ?? '') : '') ?>">
<?php foreach (['name' => 'Nombre o razón social', 'tax_id' => 'NIF / CIF', 'email' => 'Correo electrónico', 'phone' => 'Teléfono', 'address' => 'Dirección'] as $key => $label): ?>
<label><?= e($label) ?><input name="<?= e($key) ?>" type="<?= $key === 'email' ? 'email' : 'text' ?>" value="<?= e($editing[$key] ?? '') ?>" <?= $key === 'name' ? 'required' : '' ?>></label>
<?php endforeach ?><button>Guardar cliente</button> <a href="/">Limpiar formulario</a></form></section></div>
<?php endif ?></main><footer>MOBA ERP · Primera versión · Clientes</footer></body></html>
