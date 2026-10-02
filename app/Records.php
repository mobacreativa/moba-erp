<?php
declare(strict_types=1);
namespace Moba;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Versioned domain documents: one record per entity and append-only historical revisions. */
final class Records
{
    public const KINDS = ['product','supplier','brand','color','size','rate','quote','settings','template'];
    public function __construct(private PDO $db) {}

    public function all(string $kind): array
    {
        $this->kind($kind);
        $q = $this->db->prepare('SELECT id,version,document,updated_at FROM erp_records WHERE kind=? ORDER BY updated_at DESC,id');
        $q->execute([$kind]);
        return array_map($this->decode(...), $q->fetchAll(PDO::FETCH_ASSOC));
    }

    public function get(string $kind, string $id): array
    {
        $this->kind($kind);
        $q = $this->db->prepare('SELECT id,version,document,updated_at FROM erp_records WHERE kind=? AND id=?');
        $q->execute([$kind,$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new InvalidArgumentException('El registro no existe.'); }
        return $this->decode($row);
    }

    public function history(string $kind, string $id): array
    {
        $this->kind($kind);
        $q = $this->db->prepare('SELECT id,version,document,created_at AS updated_at FROM erp_history WHERE kind=? AND id=? ORDER BY version DESC');
        $q->execute([$kind,$id]);
        return array_map($this->decode(...), $q->fetchAll(PDO::FETCH_ASSOC));
    }

    public function save(string $kind, array $data, string $id = '', int $version = 0): array
    {
        $this->kind($kind);
        unset($data['id'], $data['version'], $data['updated_at']);
        $data = $this->validate($kind, $data);
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 2000000) { throw new InvalidArgumentException('Registro demasiado grande.'); }
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) { $this->db->beginTransaction(); }
        try {
            if ($id === '') {
                $id = bin2hex(random_bytes(16));
                $version = 1;
                $q = $this->db->prepare('INSERT INTO erp_records(kind,id,version,document) VALUES (?,?,?,?)');
                $q->execute([$kind,$id,$version,$json]);
            } else {
                $q = $this->db->prepare('UPDATE erp_records SET document=?,version=version+1 WHERE kind=? AND id=? AND version=?');
                $q->execute([$json,$kind,$id,$version]);
                if ($q->rowCount() !== 1) { throw new RuntimeException('Otro usuario o pestaña ha cambiado este registro. Recarga antes de guardar.', 409); }
                $version++;
            }
            $this->db->prepare('INSERT INTO erp_history(kind,id,version,document) VALUES (?,?,?,?)')->execute([$kind,$id,$version,$json]);
            if ($ownsTransaction) { $this->db->commit(); }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
        return $data + ['id'=>$id,'version'=>$version];
    }

    private function decode(array $row): array
    {
        return array_merge(json_decode($row['document'], true, 64, JSON_THROW_ON_ERROR), ['id'=>$row['id'],'version'=>(int)$row['version'],'updated_at'=>$row['updated_at']]);
    }

    private function kind(string $kind): void
    {
        if (!in_array($kind, self::KINDS, true)) { throw new InvalidArgumentException('Tipo de registro no válido.'); }
    }

    public function validate(string $kind, array $data): array
    {
        if ($kind === 'quote') { return $data; } // Only Quotes may write this kind through the API.
        if (!is_string($data['name'] ?? null) || trim($data['name']) === '' || mb_strlen($data['name']) > 190) { throw new InvalidArgumentException('Indica un nombre de hasta 190 caracteres.'); }
        $data['name'] = trim($data['name']);
        $data['active'] = (bool) ($data['active'] ?? true);
        foreach (['reference','category','code','zone','size','technique','unit','basis','email','phone','tax_id','address','notes','hex','brand_id','supplier_id','color_supplier_id'] as $key) {
            if (isset($data[$key]) && (!is_string($data[$key]) || mb_strlen($data[$key]) > 2000)) { throw new InvalidArgumentException("Campo $key no válido."); }
        }
        if ($kind === 'color') {
            $this->get('supplier', (string) ($data['supplier_id'] ?? ''));
            if (trim($data['code'] ?? '') === '' || !preg_match('/^#[a-fA-F0-9]{6}$/D', $data['hex'] ?? '')) { throw new InvalidArgumentException('Código y color HEX obligatorios.'); }
        }
        if ($kind === 'product') {
            foreach (['cost','pvp'] as $key) { $data[$key] = ($data[$key] ?? '') === '' || $data[$key] === null ? null : Pricing::number($data[$key], $key); }
            $data['min_qty'] = Pricing::quantity($data['min_qty'] ?? 1);
            $data['vat'] = Pricing::number($data['vat'] ?? 21, 'IVA', 100);
            $data['margin'] = Pricing::number($data['margin'] ?? 35, 'Margen', 1000);
            $data['mode'] = (string) ($data['mode'] ?? 'margin');
            Pricing::sale(0, $data['margin'], $data['mode']);
            if (!in_array($data['calculation'] ?? 'unit', ['unit','area','fixed'], true)) { throw new InvalidArgumentException('Cálculo no válido.'); }
            foreach (['brand_id'=>'brand','supplier_id'=>'supplier','color_supplier_id'=>'supplier'] as $key=>$type) {
                if (!empty($data[$key])) { $this->get($type, $data[$key]); }
            }
            foreach (['color_ids'=>'color','size_ids'=>'size'] as $key=>$type) {
                if (!is_array($data[$key] ?? [])) { throw new InvalidArgumentException('Selección no válida.'); }
                $data[$key] = array_values(array_unique($data[$key] ?? []));
                foreach ($data[$key] as $id) {
                    $r = $this->get($type, (string)$id);
                    if ($type === 'color' && $r['supplier_id'] !== ($data['color_supplier_id'] ?? $data['supplier_id'] ?? '')) { throw new InvalidArgumentException('El color no pertenece a la biblioteca seleccionada.'); }
                }
            }
            if (!is_array($data['techniques'] ?? [])) { throw new InvalidArgumentException('Técnicas no válidas.'); }
            foreach ($data['techniques'] ?? [] as $t) { if (!is_string($t) || mb_strlen($t) > 80) { throw new InvalidArgumentException('Técnica no válida.'); } }
            foreach ($data['margin_rules'] ?? [] as $r) {
                Pricing::quantity($r['min_qty'] ?? 0);
                if (!empty($r['max_qty']) && Pricing::quantity($r['max_qty']) < $r['min_qty']) { throw new InvalidArgumentException('Tramo de margen incorrecto.'); }
                Pricing::sale(0, Pricing::number($r['margin'] ?? null,'Margen',1000),$data['mode']);
            }
        }
        if ($kind === 'rate') {
            $data['amount'] = Pricing::number($data['amount'] ?? null, 'Importe');
            $data['min_qty'] = Pricing::quantity($data['min_qty'] ?? 1);
            $data['max_qty'] = empty($data['max_qty']) ? null : Pricing::quantity($data['max_qty']);
            if ($data['max_qty'] !== null && $data['max_qty'] < $data['min_qty']) { throw new InvalidArgumentException('Tramo de cantidad incorrecto.'); }
            if (!in_array($data['basis'] ?? '', ['cost','sale'],true)) { throw new InvalidArgumentException('Indica si es coste o tarifa de venta.'); }
            if (!in_array($data['unit'] ?? '', ['unit','m2','cm2','minute'],true)) { throw new InvalidArgumentException('Unidad incorrecta.'); }
        }
        return $data;
    }
}
