<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
fwrite(STDERR, "Introduce la contraseña (la entrada puede ser visible en tu terminal):\n");
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 12) { fwrite(STDERR, "Usa al menos 12 caracteres.\n"); exit(1); }
echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;
