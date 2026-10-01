<?php
declare(strict_types=1);
// Solo prepara una instancia desechable para el workflow; nunca usar en producción.
if (PHP_SAPI !== 'cli' || getenv('CI') !== 'true') { exit(1); }
$path = dirname(__DIR__) . '/config/local.php';
if (file_exists($path)) { throw new RuntimeException('No se sobrescriben configuraciones existentes.'); }
$config = [
    'dsn' => getenv('MOBA_TEST_DSN'),
    'user' => getenv('MOBA_TEST_USER'),
    'password' => getenv('MOBA_TEST_PASSWORD'),
    'admin_username' => 'ci-admin',
    'admin_password_hash' => password_hash('ci-password-only', PASSWORD_DEFAULT),
    'secure_cookie' => false,
];
file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
