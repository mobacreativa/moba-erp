<?php
declare(strict_types=1);
namespace Moba;

/** Avisa por correo a quien recibe una orden o tarea asignada desde el ERP. Sin precios ni datos de clientes. */
final class Notifier
{
    private string $baseUrl;

    public function __construct(private Users $users, private Mailer $mailer, array $config = [])
    {
        $url = (string) (($config['mail']['base_url'] ?? null) ?: 'https://erp.mobacreativa.com');
        $this->baseUrl = rtrim(filter_var($url, FILTER_VALIDATE_URL) ? $url : 'https://erp.mobacreativa.com', '/');
    }

    /** true si la asignación es nueva o cambia de persona y no es el propio usuario quien se la asigna. */
    public static function shouldNotify(?array $old, array $new, array $actor): bool
    {
        $to = (int) ($new['assignee_id'] ?? -1);
        if ($to < 0 || $to === (int) ($actor['id'] ?? -2)) { return false; }
        return $old === null || (int) ($old['assignee_id'] ?? -1) !== $to;
    }

    public function order(array $order, array $actor): bool
    {
        $user = $this->recipient((int) $order['assignee_id']);
        if (!$user) { return false; }
        $lines = [];
        foreach ($order['items'] ?? [] as $item) { $lines[] = '- ' . $item['product'] . ' × ' . $item['quantity']; }
        $due = self::date((string) ($order['due_date'] ?? ''));
        $body = "Hola {$user['name']}:\n\n"
            . ($actor['name'] ?? 'Alguien') . " te ha asignado una orden de trabajo en MOBA ERP.\n\n"
            . 'Orden: ' . $order['name'] . "\n"
            . 'Entrega estimada: ' . ($due ?: 'sin fecha') . "\n"
            . ($lines ? "Productos:\n" . implode("\n", array_slice($lines, 0, 30)) . (count($lines) > 30 ? "\n- …" : '') . "\n" : '')
            . "\nVer la orden: {$this->baseUrl}/?view=team\n\n"
            . "Este aviso se envía automáticamente; no respondas a este correo.\n";
        return $this->mailer->send($user['email'], 'Nueva orden asignada: ' . $order['name'], $body);
    }

    public function task(array $task, array $actor): bool
    {
        $user = $this->recipient((int) $task['assignee_id']);
        if (!$user) { return false; }
        $body = "Hola {$user['name']}:\n\n"
            . ($actor['name'] ?? 'Alguien') . " te ha asignado una tarea en MOBA ERP.\n\n"
            . 'Tarea: ' . $task['name'] . "\n"
            . "\nVer la tarea: {$this->baseUrl}/?view=tasks\n\n"
            . "Este aviso se envía automáticamente; no respondas a este correo.\n";
        return $this->mailer->send($user['email'], 'Nueva tarea asignada: ' . $task['name'], $body);
    }

    private function recipient(int $id): ?array
    {
        try { $user = $id === 0 ? $this->users->root() : $this->users->get($id); } catch (\InvalidArgumentException) { return null; }
        $email = (string) ($user['email'] ?? '');
        return $user['active'] && filter_var($email, FILTER_VALIDATE_EMAIL) ? $user : null;
    }

    private static function date(string $date): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : '';
    }
}
