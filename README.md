# MOBA ERP

Aplicación privada PHP/MariaDB para clientes, catálogo y presupuestos en erp.mobacreativa.com.

## Instalación

PHP 8.2+ con PDO MySQL y mbstring; MariaDB 10.11+. No requiere Composer.
Para una base nueva, importar database/schema.sql y después database/002_erp.sql.
Para actualizar una instalación existente, importar únicamente database/002_erp.sql.
Configurar config/local.php fuera del directorio público, conservar sus credenciales y servir exclusivamente public/ por HTTPS.

El módulo de clientes permanece en /. El nuevo espacio de trabajo está en /?view=erp y utiliza la misma sesión.

## Funcionalidad

Catálogo editable, marcas y proveedores separados, bibliotecas de colores/tallas, técnicas y tarifas por cantidad. Cálculos por unidad, superficie y personalización; distinción de margen sobre venta/recargo y coste/tarifa de venta. Presupuestos numerados, borradores, emisión, aceptación, rechazo, duplicado, histórico e impresión comercial/PDF sin costes internos.

Cada entidad se guarda como documento versionado en erp_records, con revisiones inmutables en erp_history. La actualización exige la versión leída para detectar ediciones concurrentes. Los clientes existentes se conservan. Las tablas normalizadas iniciales del catálogo se mantienen sin borrar datos; la interfaz nueva usa documentos versionados.

La importación disponible actualmente es CSV UTF-8 con vista previa. Los datos originales del prototipo se importan por separado y nunca se publican en GitHub. Los precios recuperados son referencias históricas y necesitan revisión comercial. No se presenta un coste desconocido como cero ni se calcula beneficio cuando faltan costes.

## Verificación

GitHub Actions ejecuta sintaxis, pruebas unitarias, integración MariaDB y HTTP en PHP 8.2/8.3. En PHP 8.2 también verifica el recorrido de navegador y genera un PDF y capturas con datos ficticios.

No están implementados facturación, cobros, almacén ni producción. La importación XLSX directa, las plantillas reutilizables y los configuradores especializados por puntadas/serigrafía siguen pendientes; no deben presentarse como funciones terminadas.
