<?php
declare(strict_types=1);
namespace Moba;

use InvalidArgumentException;

/** Pure calculation engine. Values are snapshotted by Quotes, never recalculated on read. */
final class Pricing
{
    public static function number(mixed $value, string $label, float $max = 100000000, float $min = 0): float
    {
        if (!is_scalar($value) || !is_numeric($value)) { throw new InvalidArgumentException("$label: introduce un número."); }
        $number = (float) $value;
        if (!is_finite($number) || $number < $min || $number > $max) { throw new InvalidArgumentException("$label: valor fuera de rango."); }
        return $number;
    }

    public static function quantity(mixed $value): int
    {
        $number = self::number($value, 'Cantidad', 1000000, 1);
        if (floor($number) !== $number) { throw new InvalidArgumentException('La cantidad debe ser entera.'); }
        return (int) $number;
    }

    public static function sale(float $cost, float $percent, string $mode): float
    {
        self::number($cost, 'Coste');
        self::number($percent, 'Porcentaje', $mode === 'margin' ? 99.99 : 1000);
        return match ($mode) {
            'margin' => $cost / (1 - $percent / 100),
            'markup' => $cost * (1 + $percent / 100),
            default => throw new InvalidArgumentException('Tipo de margen no válido.'),
        };
    }

    /** Reject ambiguous rules rather than choosing one silently. */
    public static function rate(array $rates, string $technique, string $zone, string $size, int $quantity): array
    {
        $matches = array_values(array_filter($rates, static fn(array $r): bool =>
            ($r['active'] ?? true) && ($r['technique'] ?? '') === $technique &&
            ($r['zone'] ?? '') === $zone && ($r['size'] ?? '') === $size &&
            $quantity >= (int) ($r['min_qty'] ?? 1) &&
            (empty($r['max_qty']) || $quantity <= (int) $r['max_qty'])));
        if (count($matches) !== 1) {
            throw new InvalidArgumentException(count($matches) ? 'Hay tarifas solapadas; corrige sus tramos.' : "Falta tarifa para $technique · $zone · $size · $quantity unidades.");
        }
        return $matches[0];
    }

    public static function line(array $product, array $input, array $rates): array
    {
        $quantity = self::quantity($input['quantity'] ?? 0);
        if ($quantity < (int) ($product['min_qty'] ?? 1)) { throw new InvalidArgumentException('No se alcanza la cantidad mínima del producto.'); }
        $basis = $product['calculation'] ?? 'unit';
        $factor = 1.0;
        if ($basis === 'area') {
            $factor = self::number($input['width_cm'] ?? 0, 'Ancho', 100000, 0.01) * self::number($input['height_cm'] ?? 0, 'Alto', 100000, 0.01) / 10000;
        }
        $cost = isset($input['unit_cost']) && $input['unit_cost'] !== '' ? self::number($input['unit_cost'], 'Coste manual') : ($product['cost'] ?? null);
        if ($cost !== null) { $cost = self::number($cost, 'Coste') * $factor; }
        $breakdown = ['product' => $cost, 'factor' => $factor, 'personalization' => []];
        $saleAdd = 0.0;
        $costAdd = 0.0;
        $costKnown = $cost !== null;
        $zones = [];
        $zoneKeys = [];
        $technical = [];
        foreach ($input['personalizations'] ?? [] as $p) {
            if (!is_array($p)) { throw new InvalidArgumentException('Personalización incorrecta.'); }
            $technique = (string) ($p['technique'] ?? '');
            $zoneKey = $technique . ':' . (string)($p['zone'] ?? '');
            if (isset($zoneKeys[$zoneKey])) { throw new InvalidArgumentException('Selecciona un único tamaño por técnica y zona.'); }
            $zoneKeys[$zoneKey] = true;
            if (!in_array($technique, $product['techniques'] ?? [], true)) { throw new InvalidArgumentException('Esta técnica no está permitida para el producto.'); }
            $r = self::rate($rates, $technique, (string) ($p['zone'] ?? ''), (string) ($p['size'] ?? ''), $quantity);
            $multiplier = match ($r['unit'] ?? 'unit') {
                'unit' => 1.0,
                'm2' => $factor,
                'cm2' => self::number($p['width_cm'] ?? 0, 'Ancho impresión', 100000, 0.01) * self::number($p['height_cm'] ?? 0, 'Alto impresión', 100000, 0.01),
                'minute' => self::number($p['minutes'] ?? 0, 'Minutos', 100000, 0.01),
                default => throw new InvalidArgumentException('Unidad de tarifa no válida.'),
            };
            $value = self::number($r['amount'] ?? null, 'Tarifa') * $multiplier;
            if (($r['basis'] ?? '') === 'cost') {
                $costAdd += $value;
                if ($cost !== null) { $cost += $value; }
            } else {
                $saleAdd += $value;
                // A sale tariff does not tell us the cost of producing the personalization.
                $costKnown = false;
            }
            $breakdown['personalization'][] = ['rate' => $r, 'multiplier' => $multiplier, 'amount' => $value];
            $zones[] = trim($technique . ' ' . ($p['zone'] ?? ''));
            $technical[] = $p;
        }
        $handling = self::number($input['handling'] ?? 0, 'Manipulación');
        $setup = self::number($input['setup'] ?? 0, 'Preparación');
        $design = self::number($input['design'] ?? 0, 'Diseño');
        $supplements = self::number($input['supplements_total'] ?? 0, 'Suplementos');
        $costAdd += $handling + ($setup + $design + $supplements) / $quantity;
        if ($cost !== null) { $cost += $handling + ($setup + $design + $supplements) / $quantity; }
        $margin = self::number($input['margin'] ?? $product['margin'] ?? 35, 'Margen', 1000);
        foreach ($product['margin_rules'] ?? [] as $rule) {
            if (!isset($input['margin']) && $quantity >= $rule['min_qty'] && (empty($rule['max_qty']) || $quantity <= $rule['max_qty'])) { $margin = (float) $rule['margin']; }
        }
        $mode = (string) ($input['mode'] ?? $product['mode'] ?? 'margin');
        // Validate the percentage even with a manual price.
        self::sale(0, $margin, $mode);
        $recommended = $cost === null ? null : self::sale($cost, $margin, $mode) + $saleAdd;
        $manual = $input['price'] ?? '';
        $reference = $product['pvp'] ?? null;
        if ($manual !== '' && $manual !== null) { $price = self::number($manual, 'PVP manual'); }
        elseif ($reference !== null) { $price = self::number($reference, 'PVP catálogo') * $factor + self::sale($costAdd, $margin, $mode) + $saleAdd; }
        elseif ($recommended !== null) { $price = $recommended; }
        else { throw new InvalidArgumentException('Faltan costes para calcular el precio. Indica un PVP manual o completa costes.'); }
        $price = round($price, 2);
        $discount = self::number($input['discount'] ?? 0, 'Descuento', 100);
        $vat = self::number($input['vat'] ?? $product['vat'] ?? 21, 'IVA', 100);
        $base = round($price * $quantity * (1 - $discount / 100), 2);
        $tax = round($base * $vat / 100, 2);
        $totalCost = !$costKnown || $cost === null ? null : round($cost * $quantity, 2);
        $profit = $totalCost === null ? null : round($base - $totalCost, 2);
        $realMargin = $profit === null || $base == 0 ? null : round(100 * $profit / $base, 2);
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') { $description = $product['name'] . ($zones ? ' con ' . implode(' y ', $zones) : ''); }
        if (mb_strlen($description) > 1000) { throw new InvalidArgumentException('Descripción demasiado larga.'); }
        return [
            'description' => $description, 'quantity' => $quantity, 'price' => $price,
            'discount' => $discount, 'vat' => $vat, 'base' => $base, 'tax' => $tax, 'total' => round($base + $tax, 2),
            'cost' => $totalCost, 'profit' => $profit, 'real_margin' => $realMargin, 'recommended' => $recommended === null ? null : round($recommended, 2),
            'product_snapshot' => $product, 'input' => $input, 'technical' => $technical,
            'breakdown' => $breakdown + ['handling' => $handling, 'setup' => $setup, 'design' => $design, 'supplements' => $supplements, 'mode' => $mode, 'margin' => $margin],
        ];
    }
}
