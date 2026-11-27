<?php
declare(strict_types=1);
namespace Moba;

use InvalidArgumentException;
use PDO;

/**
 * Recibe el webhook «Creación de pedido» (orders/create) de Shopify y crea una orden de trabajo.
 * Configuración privada en config/local.php:
 *   'shopify' => ['webhook_secret' => '…', 'assignee_username' => 'usuario', 'shop_domain' => 'tienda.myshopify.com']
 */
final class ShopifyWebhook
{
    public const TOPIC = 'orders/create';

    public function __construct(private PDO $db, private Records $records, private Users $users, private WorkOrders $orders, private Customers $customers, private array $config) {}

    public static function verify(string $body, string $hmac, string $secret): bool
    {
        if ($secret === '' || $hmac === '') { return false; }
        return hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $hmac);
    }

    /** @return array{status:int, result:string, order_id?:string} */
    public function handle(string $body, array $headers): array
    {
        $cfg = $this->config['shopify'] ?? [];
        $secret = is_string($cfg['webhook_secret'] ?? null) ? $cfg['webhook_secret'] : '';
        if ($secret === '') { return ['status' => 503, 'result' => 'Webhook de Shopify sin configurar.']; }
        if (!self::verify($body, (string) ($headers['hmac'] ?? ''), $secret)) { return ['status' => 401, 'result' => 'Firma no válida.']; }
        $shop = (string) ($cfg['shop_domain'] ?? '');
        if ($shop !== '' && strcasecmp($shop, (string) ($headers['shop'] ?? '')) !== 0) { return ['status' => 401, 'result' => 'Tienda no autorizada.']; }
        if (($headers['topic'] ?? '') !== self::TOPIC) { return ['status' => 200, 'result' => 'Evento ignorado.']; }
        $order = json_decode($body, true);
        if (!is_array($order) || !isset($order['id']) || !is_scalar($order['id'])) { return ['status' => 400, 'result' => 'Pedido no válido.']; }
        $assignee = $this->assignee((string) ($cfg['assignee_username'] ?? ''));

        // Evita duplicados aunque Shopify reenvíe el aviso o lleguen dos a la vez.
        $this->db->query("SELECT GET_LOCK('moba_shopify_webhook', 10)");
        try {
            $shopifyId = (string) $order['id'];
            foreach ($this->records->all('work_order') as $existing) {
                if ((string) ($existing['shopify_order_id'] ?? '') === $shopifyId) { return ['status' => 200, 'result' => 'Pedido ya registrado.', 'order_id' => $existing['id']]; }
            }
            $customerId = $this->customer($order);
            $label = self::text($order['name'] ?? ('#' . ($order['order_number'] ?? $shopifyId)), 40);
            $buyer = self::buyerName($order);
            $saved = $this->orders->save([
                'name' => self::text('Shopify ' . $label . ($buyer !== '' ? ' · ' . $buyer : ''), 190),
                'instructions' => self::instructions($order, $label),
                'due_date' => '',
                'quote_id' => '',
                'customer_id' => $customerId,
                'assignee_id' => $assignee['id'],
                'items' => self::items($order),
            ], ['id' => 0, 'role' => 'admin', 'username' => 'shopify', 'name' => 'Shopify'], ['source' => 'shopify', 'shopify_order_id' => $shopifyId, 'shopify_order_name' => $label]);
            return ['status' => 200, 'result' => 'Orden creada.', 'order_id' => $saved['id']];
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('moba_shopify_webhook')");
        }
    }

    private function assignee(string $username): array
    {
        foreach ($this->users->all() as $user) {
            if ($username !== '' && $user['username'] === $username && $user['active'] && in_array($user['role'], ['admin', 'production'], true)) { return $user; }
        }
        throw new InvalidArgumentException('Configura en shopify.assignee_username un usuario activo de Producción o Administrador creado en la pantalla Usuarios.');
    }

    private function customer(array $order): int
    {
        $c = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $addr = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : (is_array($order['billing_address'] ?? null) ? $order['billing_address'] : []);
        $email = self::text($order['email'] ?? ($c['email'] ?? ''), 190);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $email = ''; }
        if ($email !== '') {
            $q = $this->db->prepare('SELECT id FROM customers WHERE LOWER(email)=LOWER(?) ORDER BY id LIMIT 1');
            $q->execute([$email]);
            $id = $q->fetchColumn();
            if ($id !== false) { return (int) $id; }
        }
        $name = self::buyerName($order);
        if ($name === '') { return 0; }
        return $this->customers->save([
            'name' => self::text($name, 160),
            'tax_id' => '',
            'email' => $email,
            'phone' => self::text($order['phone'] ?? ($addr['phone'] ?? ($c['phone'] ?? '')), 40),
            'address' => self::text(self::address($addr), 500),
        ]);
    }

    private static function buyerName(array $order): string
    {
        $c = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        foreach (['billing_address', 'shipping_address'] as $key) {
            $a = is_array($order[$key] ?? null) ? $order[$key] : [];
            if (self::text($a['company'] ?? '', 160) !== '') { return self::text($a['company'], 160); }
        }
        $full = trim(self::text($c['first_name'] ?? '', 80) . ' ' . self::text($c['last_name'] ?? '', 80));
        if ($full !== '') { return $full; }
        foreach (['shipping_address', 'billing_address'] as $key) {
            $a = is_array($order[$key] ?? null) ? $order[$key] : [];
            if (self::text($a['name'] ?? '', 160) !== '') { return self::text($a['name'], 160); }
        }
        return '';
    }

    private static function address(array $a): string
    {
        $line = fn(array $keys) => implode(' ', array_filter(array_map(fn($k) => self::text($a[$k] ?? '', 120), $keys)));
        return implode(', ', array_filter([$line(['address1']), $line(['address2']), $line(['zip', 'city']), $line(['province']), $line(['country'])]));
    }

    private static function items(array $order): array
    {
        $items = [];
        foreach (array_slice(is_array($order['line_items'] ?? null) ? $order['line_items'] : [], 0, 100) as $line) {
            if (!is_array($line)) { continue; }
            $title = self::text($line['title'] ?? ($line['name'] ?? ''), 120);
            $variant = self::text($line['variant_title'] ?? '', 60);
            $sku = self::text($line['sku'] ?? '', 40);
            $product = self::text(trim($title . ($variant !== '' ? ' — ' . $variant : '') . ($sku !== '' ? ' (' . $sku . ')' : '')), 190);
            $qty = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
            if ($product === '' || $qty === false) { continue; }
            $items[] = ['product' => $product, 'quantity' => $qty];
        }
        return $items;
    }

    /** Instrucciones de producción sin precios: personalizaciones, notas y envío. */
    private static function instructions(array $order, string $label): string
    {
        $out = ['Pedido web ' . $label . ' recibido de Shopify' . (self::text($order['created_at'] ?? '', 30) !== '' ? ' el ' . substr((string) $order['created_at'], 0, 10) : '') . '.'];
        $pay = self::text($order['financial_status'] ?? '', 30);
        if ($pay !== '') { $out[] = 'Estado del pago en Shopify al crearse: ' . (['paid' => 'pagado', 'pending' => 'pendiente', 'authorized' => 'autorizado', 'partially_paid' => 'pagado parcialmente', 'refunded' => 'reembolsado', 'voided' => 'anulado'][$pay] ?? $pay) . '.'; }
        foreach (is_array($order['line_items'] ?? null) ? $order['line_items'] : [] as $line) {
            $props = [];
            foreach (is_array($line['properties'] ?? null) ? $line['properties'] : [] as $p) {
                $n = self::text($p['name'] ?? '', 80); $v = self::text($p['value'] ?? '', 500);
                if ($n !== '' && $v !== '' && !str_starts_with($n, '_')) { $props[] = $n . ': ' . $v; }
            }
            if ($props) { $out[] = "\n" . self::text($line['title'] ?? '', 120) . ' — personalización:' . "\n- " . implode("\n- ", $props); }
        }
        $note = self::text($order['note'] ?? '', 5000);
        if ($note !== '') { $out[] = "\nNota del cliente:\n" . $note; }
        $attrs = [];
        foreach (is_array($order['note_attributes'] ?? null) ? $order['note_attributes'] : [] as $a) {
            $n = self::text($a['name'] ?? '', 80); $v = self::text($a['value'] ?? '', 500);
            if ($n !== '' && $v !== '' && !str_starts_with($n, '_')) { $attrs[] = $n . ': ' . $v; }
        }
        if ($attrs) { $out[] = "\nDatos adicionales:\n- " . implode("\n- ", $attrs); }
        $ship = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : [];
        $method = [];
        foreach (is_array($order['shipping_lines'] ?? null) ? $order['shipping_lines'] : [] as $s) { if (self::text($s['title'] ?? '', 120) !== '') { $method[] = self::text($s['title'], 120); } }
        if ($ship || $method) { $out[] = "\nEnvío" . ($method ? ' (' . implode(', ', $method) . ')' : '') . ':' . ($ship ? "\n" . trim(self::text($ship['name'] ?? '', 160) . "\n" . self::address($ship)) : ''); }
        return mb_substr(implode("\n", $out), 0, 20000);
    }

    private static function text(mixed $value, int $max): string
    {
        if (!is_scalar($value)) { return ''; }
        $clean = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value));
        return mb_strcut($clean, 0, $max, 'UTF-8');
    }
}
