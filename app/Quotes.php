<?php
declare(strict_types=1);
namespace Moba;
use PDO;
use InvalidArgumentException;

final class Quotes
{
    public function __construct(private PDO $db, private Records $records) {}

    public function calculate(array $input): array
    {
        $product = $this->records->get('product', (string) ($input['product_id'] ?? ''));
        if (!$product['active']) { throw new InvalidArgumentException('Producto desactivado.'); }
        $distribution = $input['distribution'] ?? [];
        if (!is_array($distribution) || count($distribution) > 1000) { throw new InvalidArgumentException('Desglose de tallas incorrecto.'); }
        if ($distribution) {
            $quantity = 0;
            $seen = [];
            foreach ($distribution as &$entry) {
                $colorId = (string) ($entry['color_id'] ?? '');
                $sizeId = (string) ($entry['size_id'] ?? '');
                if (!in_array($colorId,$product['color_ids'] ?? [],true) || !in_array($sizeId,$product['size_ids'] ?? [],true)) { throw new InvalidArgumentException('Color o talla no disponible para este modelo.'); }
                $key = $colorId . ':' . $sizeId;
                if (isset($seen[$key])) { throw new InvalidArgumentException('Combinación de color y talla duplicada.'); }
                $seen[$key] = true;
                $color = $this->records->get('color',$colorId);
                $size = $this->records->get('size',$sizeId);
                if (!$color['active'] || !$size['active']) { throw new InvalidArgumentException('Color o talla desactivado.'); }
                $quantity += Pricing::quantity($entry['quantity'] ?? 0);
                $entry['color'] = $color['code'] . ' · ' . $color['name'];
                $entry['size'] = $size['name'];
            }
            unset($entry);
            $input['quantity'] = $quantity;
            $input['distribution'] = $distribution;
        }
        return Pricing::line($product,$input,$this->records->all('rate'));
    }

    public function save(array $input, string $id, int $version): array
    {
        $customerId = filter_var($input['customer_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$customerId) { throw new InvalidArgumentException('Selecciona un cliente.'); }
        $q = $this->db->prepare('SELECT * FROM customers WHERE id=?');
        $q->execute([$customerId]);
        $customer = $q->fetch(PDO::FETCH_ASSOC);
        if (!$customer) { throw new InvalidArgumentException('Cliente no encontrado.'); }
        $old = $id === '' ? null : $this->records->get('quote',$id);
        if ($old && $old['status'] !== 'draft') { throw new InvalidArgumentException('Duplica el presupuesto para modificar uno emitido o aceptado.'); }
        if (!is_array($input['lines'] ?? null) || !$input['lines'] || count($input['lines']) > 200) { throw new InvalidArgumentException('Añade entre 1 y 200 líneas.'); }
        $lines = [];
        foreach ($input['lines'] as $line) {
            // Unmodified saved lines keep their historical calculation. New/edited lines recalculate explicitly.
            if (isset($line['saved_index']) && $old) {
                $index = filter_var($line['saved_index'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
                if ($index === false || !isset($old['lines'][$index])) { throw new InvalidArgumentException('Línea histórica no válida.'); }
                $lines[] = $old['lines'][$index];
            } else { $lines[] = $this->calculate($line); }
        }
        $date = (string)($input['date'] ?? '');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) { throw new InvalidArgumentException('Fecha no válida.'); }
        $doc = ['name'=>$old['name'] ?? '', 'customer_id'=>$customerId,'customer'=>$customer,'date'=>$date,
            'valid_days'=>Pricing::quantity($input['valid_days'] ?? 30), 'notes'=>substr((string)($input['notes'] ?? ''),0,10000),
            'payment'=>substr((string)($input['payment'] ?? ''),0,2000),'status'=>'draft','lines'=>$lines,
            'base'=>round(array_sum(array_column($lines,'base')),2),'tax'=>round(array_sum(array_column($lines,'tax')),2),
            'total'=>round(array_sum(array_column($lines,'total')),2)];
        $settings = $this->records->all('settings');
        $doc['company'] = $old['company'] ?? ($settings[0] ?? ['name'=>'MOBA CREATIVA']);
        $this->db->beginTransaction();
        try {
            if (!$old) {
                $year = (int) $parsed->format('Y');
                $this->db->prepare('INSERT IGNORE INTO erp_sequences(year,last_number) VALUES (?,0)')->execute([$year]);
                $this->db->prepare('UPDATE erp_sequences SET last_number=last_number+1 WHERE year=?')->execute([$year]);
                $seq = $this->db->prepare('SELECT last_number FROM erp_sequences WHERE year=?');
                $seq->execute([$year]);
                $doc['name'] = sprintf('%d/%05d',$year,(int)$seq->fetchColumn());
            }
            $saved = $this->records->save('quote',$doc,$id,$version);
            $this->db->commit();
            return $saved;
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function status(string $id,int $version,string $status): array
    {
        $doc = $this->records->get('quote',$id);
        $allowed = ['draft'=>['issued','cancelled'],'issued'=>['accepted','rejected','cancelled'],'accepted'=>['cancelled'],'rejected'=>[],'cancelled'=>[]];
        if (!in_array($status,$allowed[$doc['status']] ?? [],true)) { throw new InvalidArgumentException('Cambio de estado no permitido.'); }
        $doc['status'] = $status;
        return $this->records->save('quote',$doc,$id,$version);
    }
}
