# Especificación recuperada — 2 de octubre de 2026

Fuente: conversación «Diseñar ERP presupuestos», del 30 de septiembre al 1 de octubre, leída completa mediante el historial de ChatGPT. Último prototipo recuperado: V1.3.2 Colores Proveedor. Los datos comerciales del prototipo se mantienen fuera del repositorio público.

## Alcance aprobado

- Aplicación privada PHP/MariaDB en erp.mobacreativa.com. Mantener web principal y correo.
- Inicio, clientes, productos/servicios, tarifas/costes, presupuestos y configuración.
- Catálogo con categoría, marca y proveedor separados, referencia, compra, PVP de referencia/manual, mínimo, IVA, técnicas permitidas, comentarios y márgenes por cantidad.
- Una prenda por modelo, sin duplicarla por personalización. Varias ofertas de proveedor y costes históricos.
- Bibliotecas editables de colores por proveedor (código, nombre, HEX orientativo, activo). Asignación visual de colores por modelo y seleccionar/deseleccionar todos.
- Biblioteca editable de tallas; selección visual por modelo. Adulto XS–5XL e infantil 1/2–11/12 como valores iniciales editables. Reparto color × talla × unidades; suplementos opcionales.
- Textil + DTF: zonas independientes pecho, espalda y mangas, tamaño y tramo de cantidad. Tarifas centralizadas editables; sin tarifa aplicable no suponer coste cero.
- Coste prenda + personalización + manipulación por unidad + preparación/diseño por trabajo. Margen sobre venta y recargo sobre coste son cálculos distintos. PVP recomendado y manual, beneficio y margen real; costes desconocidos no equivalen a cero.
- Presupuestos con cliente, fecha, número, validez, observaciones, pago, líneas, cantidades, descuento, base/IVA/total. Guardar, recuperar, duplicar, revisar y marcar aceptado. PDF con descripción comercial limpia, sin costes ni medidas DTF internas.
- Conservar datos técnicos y valores calculados en cada versión; cambios posteriores del catálogo no recalculan presupuestos históricos.
- Importar productos desde Excel/CSV con vista previa y confirmación. Conservar originales.
- Fórmulas extensibles: producto + personalización, medidas (m²/cm²), precio cerrado/escalado; bordado por puntadas y láser por tiempo; tarifas profesionales separadas de costes internos.

## Criterios y asuntos no cerrados en el hilo

- Los tramos y márgenes mostrados como ejemplos no son tarifas aprobadas. Los datos del último prototipo son referencias históricas editables; no inferir que una tarifa de venta profesional sea un coste de producción.
- Los configuradores específicos de bordado, serigrafía, láser y gran formato estaban pendientes de afinar con casos reales. No inventar costes, precios, razón social ni condiciones.
- Órdenes de trabajo, producción, facturación, cobros, estadísticas y almacén eran fases futuras, no funcionalidades terminadas del prototipo.

## Validaciones de aceptación

1. Crear prenda con proveedor y marca diferentes y seleccionar colores y tallas.
2. Presupuestar dos colores y varias tallas; sumar unidades; añadir pecho y espalda con tarifas por cantidad.
3. Mostrar únicamente «prenda con DTF pecho y espalda» al cliente y guardar tamaños/costes/desglose internamente.
4. Distinguir 40 % de margen (coste / 0,60) de 40 % de recargo (coste × 1,40).
5. Cambiar coste/tarifa y comprobar que el presupuesto guardado conserva su versión anterior.
6. Evitar pérdida de cambios entre dos pestañas; comprobar sesión, CSRF, validación y escape HTML.
7. Generar PDF comercial sin información interna y conservar clientes existentes al actualizar.
