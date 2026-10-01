<?php
declare(strict_types=1);
require __DIR__ . '/unit.php';
$dsn = getenv('MOBA_TEST_DSN');
if (!$dsn) { fwrite(STDERR, "MOBA_TEST_DSN obligatorio; usar una base de pruebas.\n"); exit(1); }
$db = new PDO($dsn, getenv('MOBA_TEST_USER'), getenv('MOBA_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$db->beginTransaction();
try {
    $repo = new Moba\Customers($db);
    $id = $repo->save(['name' => "Prueba O'Connor", 'email' => 'test@example.com']);
    check($repo->find($id)['email'] === 'test@example.com', 'Alta y lectura');
    $repo->save(['name' => 'Prueba editada', 'phone' => '123456789'], $id);
    check($repo->find($id)['phone'] === '123456789', 'Edición');
    check(count(array_filter($repo->search('Prueba editada'), fn($row) => (int) $row['id'] === $id)) === 1, 'Búsqueda');
    check($repo->search("' OR 1=1 --") === [], 'Consulta parametrizada');
    try { $repo->save(['name' => 'Inexistente'], PHP_INT_MAX); throw new RuntimeException('Aceptó ID inexistente'); }
    catch (InvalidArgumentException) {}
    echo "Persistencia de clientes: OK\n";
} finally { $db->rollBack(); }
