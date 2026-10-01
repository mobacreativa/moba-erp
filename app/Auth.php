<?php
declare(strict_types=1);
namespace Moba;
final class Auth
{
    public static function verify(string $username, string $password, array $config): bool
    {
        // Verificar el hash incluso si el usuario falla para evitar una salida rápida.
        $validPassword = password_verify($password, $config['admin_password_hash']);
        return hash_equals($config['admin_username'], $username) && $validPassword;
    }
}
