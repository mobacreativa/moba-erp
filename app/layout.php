<?php
declare(strict_types=1);

/**
 * Barra lateral común a todas las pantallas autenticadas.
 * $current: home, quotes, customers, team, tasks, accounting o configuration.
 * $spa: en la pantalla de presupuestos (erp.js) Inicio, Presupuestos y Configuración
 * son botones que cambian de vista sin recargar.
 */
function moba_sidebar(array $actor, string $current, bool $spa = false): string
{
    $office = ($actor['role'] ?? '') !== 'production';
    $items = [
        ['home', 'Inicio', '/?view=erp', $office],
        ['quotes', 'Presupuestos', '/?view=erp&section=quotes', $office],
        ['customers', 'Clientes', '/?view=customers', $office],
        ['team', 'Flujo de trabajo', '/?view=team', true],
        ['tasks', 'Tareas generales', '/?view=tasks', true],
        ['accounting', 'Contabilidad', '/?view=accounting', $office],
        ['configuration', 'Configuración general', '/?view=erp&section=configuration', $office],
    ];
    $nav = '';
    foreach ($items as [$key, $label, $href, $visible]) {
        if (!$visible) { continue; }
        $active = $key === $current;
        if ($spa && in_array($key, ['home', 'quotes', 'configuration'], true)) {
            $nav .= '<button type="button" data-view="' . e($key) . '"' . ($active ? ' class="selected"' : '') . '>' . e($label) . '</button>';
        } elseif ($key === 'team' && $current === 'team') {
            $nav .= '<button type="button" id="orders" class="selected" aria-current="page">' . e($label) . '</button>';
        } else {
            $nav .= '<a href="' . e($href) . '"' . ($active ? ' class="selected" aria-current="page"' : '') . '>' . e($label) . '</a>';
        }
    }
    $who = trim((string) ($actor['name'] ?? $actor['username'] ?? ''));
    return '<aside><a class="brand" href="' . ($office ? '/?view=erp' : '/?view=team') . '"><img src="/logo-moba.png?v=20261126" alt="MOBA Creativa" width="120" height="118"><small>ERP</small></a>'
        . '<nav aria-label="Secciones">' . $nav . '</nav>'
        . '<form class="logout" method="post" action="/">'
        . ($who !== '' ? '<p class="who">' . e($who) . '</p>' : '')
        . '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '"><input type="hidden" name="action" value="logout"><button>Cerrar sesión</button></form></aside>';
}
