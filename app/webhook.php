<?php
declare(strict_types=1);
// Entrada de webhooks externos. Sin sesión ni CSRF: cada aviso se autentica con su firma.
require_once __DIR__ . '/Records.php';
require_once __DIR__ . '/WorkOrders.php';
require_once __DIR__ . '/ShopifyWebhook.php';

header('Content-Type: application/json; charset=utf-8');
$reply = static function (int $status, string $result, array $extra = []): never {
    http_response_code($status);
    echo json_encode(['ok' => $status < 300, 'result' => $result] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};
if (($_GET['webhook'] ?? '') !== 'shopify') { $reply(404, 'Webhook desconocido.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $reply(405, 'Usa POST.'); }
$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length <= 0 || $length > 2000000) { $reply(413, 'Tamaño no válido.'); }
$body = (string) file_get_contents('php://input', false, null, 0, 2000000);
try {
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $users = new \Moba\Users($db, $config);
    $records = new \Moba\Records($db);
    $hook = new \Moba\ShopifyWebhook($db, $records, $users, new \Moba\WorkOrders($records, $users, $db), new \Moba\Customers($db), $config);
    $out = $hook->handle($body, [
        'hmac' => (string) ($_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? ''),
        'topic' => (string) ($_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? ''),
        'shop' => (string) ($_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? ''),
    ]);
    $reply($out['status'], $out['result'], isset($out['order_id']) ? ['order_id' => $out['order_id']] : []);
} catch (Throwable $e) {
    // Shopify reintentará el aviso; el detalle queda solo en el log del servidor.
    error_log('MOBA shopify webhook: ' . get_class($e) . ': ' . $e->getMessage());
    $reply(500, 'No se pudo registrar el pedido. Revisa el log del servidor.');
}
