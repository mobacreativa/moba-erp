# MOBA ERP

Aplicación privada PHP/MariaDB para clientes, catálogo y presupuestos en erp.mobacreativa.com.

## Instalación

PHP 8.2+ con PDO MySQL y mbstring; MariaDB 10.11+. No requiere Composer.
Para una base nueva, importar database/schema.sql y después database/002_erp.sql y database/003_users.sql.
Para actualizar una instalación existente, importar las migraciones pendientes database/002_erp.sql y database/003_users.sql.
Configurar config/local.php fuera del directorio público, conservar sus credenciales y servir exclusivamente public/ por HTTPS.

El módulo de clientes permanece en /. El nuevo espacio de trabajo está en /?view=erp y utiliza la misma sesión.

## Funcionalidad

Catálogo editable, marcas y proveedores separados, bibliotecas de colores/tallas, técnicas y tarifas por cantidad. Cálculos por unidad, superficie y personalización; distinción de margen sobre venta/recargo y coste/tarifa de venta. Presupuestos numerados, borradores, emisión, aceptación, rechazo, duplicado, histórico e impresión comercial/PDF sin costes internos.

Cada entidad se guarda como documento versionado en erp_records, con revisiones inmutables en erp_history. La actualización exige la versión leída para detectar ediciones concurrentes. Los clientes existentes se conservan. Las tablas normalizadas iniciales del catálogo se mantienen sin borrar datos; la interfaz nueva usa documentos versionados.

La importación disponible actualmente es CSV UTF-8 con vista previa. Los datos originales del prototipo se importan por separado y nunca se publican en GitHub. Los precios recuperados son referencias históricas y necesitan revisión comercial. No se presenta un coste desconocido como cero ni se calcula beneficio cuando faltan costes.

## Verificación

GitHub Actions ejecuta sintaxis, pruebas unitarias, integración MariaDB y HTTP en PHP 8.2/8.3. En PHP 8.2 también verifica el recorrido de navegador y genera un PDF y capturas con datos ficticios.

No están implementados facturación, cobros, almacén ni planificación avanzada de producción. La importación XLSX directa, las plantillas reutilizables y los configuradores especializados por puntadas/serigrafía siguen pendientes; no deben presentarse como funciones terminadas.


## Equipo y órdenes de trabajo

Ruta /?view=team. Administrador: crea, edita y desactiva usuarios; cambia contraseñas y roles; gestiona catálogo y todas las órdenes. Comercial: clientes/presupuestos y creación/asignación de órdenes; catálogo de consulta. Producción: solo órdenes asignadas y actualización de estado/notas. Los permisos se verifican en el servidor, incluidas consultas directas e históricos.

Las contraseñas se guardan mediante password_hash, nunca se devuelven al navegador. Cambiar un usuario invalida sus sesiones anteriores; desactivarlo impide entrar. El administrador original de config/local.php se conserva y no se modifica desde esta pantalla. No se crean cuentas reales ni contraseñas predeterminadas durante la migración.

Las órdenes admiten responsable activo, fecha prevista, instrucciones, presupuesto de origen opcional y estados pendiente/en curso/bloqueada/terminada. Conservan versiones y autor de cada cambio. El operario no recibe precios ni datos de clientes; las instrucciones se redactan expresamente para producción. No hay envíos automáticos de correos.

## Tareas generales
Tareas con título, descripción, responsable y estado; búsqueda textual y filtros. El administrador ve todas; los demás, las creadas por ellos o asignadas a ellos. El creador y administrador editan la descripción y asignación; el responsable puede actualizar el estado y adjuntar archivos. Adjuntos de hasta 5 MB (20 por tarea), fuera de public, en storage/task-files. Incluir esta carpeta privada junto con la base de datos en las copias de seguridad. Descargas autenticadas con comprobación de acceso a la tarea. No hay migración adicional: utiliza erp_records y erp_history.


## Contabilidad y lector de facturas
Acceso exclusivo administrador. Importes en céntimos EUR, ingresos/gastos por fecha del documento y cobros/pagos por fecha efectiva. Calendario de pendientes por vencimiento. Los presupuestos no son ingresos automáticos. Ediciones conservan versiones; no admite pagos parciales ni importes negativos.

El adaptador Azure Document Intelligence (prebuilt-invoice, API 2024-11-30) está desactivado por defecto. Tras contratar/configurar el recurso y autorizar el envío de facturas a ese proveedor, añadir PRIVADAMENTE a config/local.php:
```php
'invoice_reader' => [
    'enabled' => true,
    'endpoint' => 'https://NOMBRE.cognitiveservices.azure.com',
    'key' => getenv('MOBA_INVOICE_READER_KEY'),
],
```
Requiere cURL y fileinfo; PHP upload_max_filesize/post_max_size deben permitir 5 MB más multipart. Nunca versionar claves. Cada archivo PDF/JPG/PNG enviado se procesa externamente con posible coste del proveedor. Los originales se guardan fuera de public en storage/invoices; descargas autenticadas de administrador. Revisar todos los campos y el total antes de registrar. Un IVA ausente queda vacío, no se presume cero. Se puede reanudar una lectura desde Facturas pendientes sin reenviar el archivo. Configuración y prueba real del proveedor pendientes hasta disponer de cuenta autorizada.


## Prueba de lectura local (16 noviembre 2026)
Contabilidad usa PDF.js para el texto de PDF y Tesseract.js en español para imágenes/páginas escaneadas. Se ejecuta en el navegador; las bibliotecas, modelos y workers se sirven desde el propio ERP, sin CDN ni llamadas OCR externas. Las reglas de campos son orientativas y conservadoras: no se inventan vencimiento ni IVA ausentes. Revisar los datos antes de guardar. Se admite una factura por archivo PDF/JPG/PNG de hasta 5 MB y 10 páginas. El original se sube al almacenamiento privado del ERP al pulsar Guardar, con descarga exclusiva para administradores. Cancelar la revisión no lo sube.

Para construir los recursos de despliegue/CI: `python tools/ocr_assets.py`. Descarga versiones fijadas de paquetes oficiales npm, verifica SHA-512 y copia únicamente archivos de runtime/licencias a public/vendor/ocr (ignorado en Git pero imprescindible en despliegue). No ejecuta scripts de instalación. El OCR necesita WebAssembly y un navegador moderno; puede ser lento en equipos modestos. La política CSP de Contabilidad permite WebAssembly, workers del mismo origen e imágenes locales; no habilita JavaScript inline ni destinos remotos. Azure permanece sin configurar y no se utiliza en este flujo.

## Contabilidad y Azure · 17 noviembre 2026
Clientes y Proveedores se abren desde Contabilidad. Proveedores utiliza el catálogo existente (NIF, contacto, dirección e histórico). Comerciales acceden a estas fichas, pero los movimientos, documentos y panel financiero siguen siendo exclusivos de administración.

Eliminar mueve el movimiento a Papelera: deja de contar en métricas y vencimientos, conserva su factura original e histórico y permite Restaurar. Las dos operaciones comprueban rol, CSRF y versión para evitar sobrescribir cambios concurrentes. No se borran documentos físicos.

El selector principal de factura usa Azure `prebuilt-invoice`; está deshabilitado mientras no haya credenciales. La lectura local está en una alternativa desplegable. El envío Azure es explícito al seleccionar el archivo: la factura llega al ERP y Microsoft; el movimiento solo se crea al confirmar Guardar. Si la lectura tarda, reanudar desde Facturas pendientes evita otro envío. Los campos ausentes no se inventan y la baja confianza se indica en la revisión. Pruebas de UI Azure usan respuestas simuladas, no acreditan una conexión real.

En hosting, copiar `config/invoice-reader.example.php` a `config/invoice-reader.php` y configurar endpoint, clave y enabled=true de forma privada. Este archivo está fuera de public y excluido de Git; sobreescribe únicamente invoice_reader de local.php. No incluirlo en paquetes, repositorios, capturas o logs. F0 limita a 4 MB y las primeras dos páginas; el ERP muestra esos límites y rechaza archivos que superen 4 MB. Con tier=S0 admite hasta 5 MB. Confirmar extracción con una factura de prueba y revisión antes de usar datos reales. La configuración real requiere una suscripción Azure y un recurso Document Intelligence.
