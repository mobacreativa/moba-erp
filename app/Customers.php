<?php
declare(strict_types=1);

namespace Moba;

final class Customers
{
    public function __construct(private \PDO $db) {}

    public static function validate(array $input): array
    {
        $data = [];
        foreach (['name' => 160, 'tax_id' => 40, 'email' => 190, 'phone' => 40, 'address' => 500] as $key => $limit) {
            if (isset($input[$key]) && !is_string($input[$key])) {
                throw new \InvalidArgumentException('Formato de campo incorrecto.');
            }
            $value = trim($input[$key] ?? '');
            if (strlen($value) > $limit) {
                throw new \InvalidArgumentException("El campo $key supera el límite de $limit bytes.");
            }
            $data[$key] = $value;
        }
        if ($data['name'] === '') { throw new \InvalidArgumentException('El nombre es obligatorio.'); }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo electrónico no es válido.');
        }
        return $data;
    }

    public function search(string $query): array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE name LIKE ? OR tax_id LIKE ? ORDER BY name, id LIMIT 100');
        $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $input, ?int $id = null): int
    {
        $data = self::validate($input);
        if ($id !== null) {
            if ($this->find($id) === null) { throw new \InvalidArgumentException('El cliente no existe.'); }
            $stmt = $this->db->prepare('UPDATE customers SET name=?, tax_id=?, email=?, phone=?, address=? WHERE id=?');
            $stmt->execute([...array_values($data), $id]);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO customers (name,tax_id,email,phone,address) VALUES (?,?,?,?,?)');
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }
}
