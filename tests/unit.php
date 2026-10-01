<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Customers.php';
require dirname(__DIR__) . '/app/Auth.php';
use Moba\Customers;
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$valid = Customers::validate(['name' => '  Taller MOBA  ', 'email' => 'hola@example.com']);
check($valid['name'] === 'Taller MOBA', 'Normalización del nombre');
check($valid['phone'] === '', 'Campos opcionales');
foreach ([[], ['name' => ' '], ['name' => ['bad']], ['name' => 'MOBA', 'email' => 'invalid'], ['name' => str_repeat('a', 161)]] as $bad) {
    try { Customers::validate($bad); throw new RuntimeException('Aceptó un cliente inválido'); }
    catch (InvalidArgumentException) {}
}
check(Customers::validate(['name' => "O'Connor <script>"])['name'] === "O'Connor <script>", 'Conservar texto; escapar en la vista');
echo "Validación de clientes: OK\n";
$config = ['admin_username' => 'test-admin', 'admin_password_hash' => password_hash('test-password-only', PASSWORD_DEFAULT)];
check(Moba\Auth::verify('test-admin', 'test-password-only', $config), 'Login válido');
check(!Moba\Auth::verify('wrong', 'test-password-only', $config), 'Rechazar usuario incorrecto');
check(!Moba\Auth::verify('test-admin', 'wrong', $config), 'Rechazar contraseña incorrecta');
echo "Autenticación: OK\n";
