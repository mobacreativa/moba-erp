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
