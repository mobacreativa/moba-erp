<?php
declare(strict_types=1);
namespace Moba;

/**
 * Envío de avisos por correo con mail() del hosting (SPF y DKIM del dominio en cPanel).
 * Configuración opcional en config/local.php:
 *   'mail' => ['enabled' => true, 'from' => 'noreply@mobacreativa.com', 'from_name' => 'MOBA ERP', 'base_url' => 'https://erp.mobacreativa.com']
 * Un fallo de envío nunca interrumpe el guardado: se registra en el log y se continúa.
 */
final class Mailer
{
    private string $from;
    private string $fromName;
    private bool $enabled;
    /** @var callable(string,string,string,string,string):bool */
    private $transport;

    public function __construct(array $config = [], ?callable $transport = null)
    {
        $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
        $this->enabled = ($mail['enabled'] ?? true) !== false;
        $from = (string) ($mail['from'] ?? 'noreply@mobacreativa.com');
        $this->from = filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'noreply@mobacreativa.com';
        $this->fromName = self::oneLine((string) ($mail['from_name'] ?? 'MOBA ERP')) ?: 'MOBA ERP';
        $this->transport = $transport ?? static fn(string $to, string $subject, string $body, string $headers, string $params): bool => mail($to, $subject, $body, $headers, $params);
    }

    public function send(string $to, string $subject, string $body): bool
    {
        if (!$this->enabled || !filter_var($to, FILTER_VALIDATE_EMAIL)) { return false; }
        $headers = implode("\r\n", [
            'From: ' . mb_encode_mimeheader($this->fromName, 'UTF-8') . ' <' . $this->from . '>',
            'Auto-Submitted: auto-generated',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);
        try {
            $ok = ($this->transport)($to, mb_encode_mimeheader(self::oneLine($subject), 'UTF-8'), str_replace(["\r\n", "\r"], "\n", $body), $headers, '-f' . $this->from);
        } catch (\Throwable $e) {
            $ok = false;
        }
        if (!$ok) { error_log('MOBA mail: no se pudo enviar el aviso a un usuario.'); }
        return $ok;
    }

    private static function oneLine(string $text): string
    {
        return trim(mb_substr((string) preg_replace('/[\r\n\t\x00-\x1F\x7F]+/u', ' ', $text), 0, 180));
    }
}
