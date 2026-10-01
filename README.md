# MOBA ERP

Primera base de la aplicación PHP + MariaDB/MySQL para MOBA Creativa.

Estado: módulo de clientes (alta, consulta, edición y búsqueda), acceso de administrador y esquema inicial del catálogo. Presupuestos, cálculo textil/DTF, márgenes y despliegue están pendientes. No se han recuperado todavía las reglas detalladas del diseño anterior.

## Instalación

Requisitos: PHP 8.3 o posterior con PDO MySQL y MariaDB 10.11/MySQL 8.0. No requiere Composer.

1. Crear una base de datos vacía con codificación utf8mb4.
2. Importar `database/schema.sql`.
3. Copiar `config/local.example.php` a `config/local.php` y completar la conexión, el usuario administrador y el hash de contraseña.
4. Generar el hash ejecutando `php bin/password.php` (lee la contraseña por entrada estándar; no la incluirá en el historial de comandos).
5. Configurar el dominio para que su raíz sea exclusivamente `public/`. No publicar la raíz del repositorio.
6. Servir por HTTPS y mantener `secure_cookie` en `true` en producción.

Desarrollo local: configurar `secure_cookie` en `false` y ejecutar `php -S 127.0.0.1:8080 -t public`. Abrir http://127.0.0.1:8080.

Pruebas: `php tests/unit.php`. Integración: importar el esquema en una base desechable, configurar `MOBA_TEST_DSN`, `MOBA_TEST_USER`, `MOBA_TEST_PASSWORD` y ejecutar `php tests/integration.php`. CI comprueba sintaxis, validación e integración con MariaDB.

## Decisiones y pendientes

- Marcas y proveedores son entidades distintas.
- Colores pertenecen a una marca; no se incluyen códigos Roly sin el catálogo real.
- Productos tienen variantes de color/talla y costes por proveedor fechados.
- No hay tarifas, clientes ni credenciales de ejemplo en producción.
- El acceso inicial es de un único administrador. Antes de abrirlo a más usuarios habrá que incorporar cuentas, roles y auditoría.
- Confirmar cómo se calcula DTF (dimensiones, aprovechamiento, merma, aplicación y mínimos), margen frente a recargo, redondeo, impuestos y numeración antes de implementar presupuestos.
- Los futuros presupuestos guardarán instantáneas de precios, costes y datos comerciales para conservar el histórico.
- Pendientes: gestión visual del catálogo, presupuestos, motor de costes, exportación y despliegue en erp.mobacreativa.com.

Antes de producción, comprobar copia/restauración de base de datos y configuración HTTPS en el hosting. Este repositorio no despliega automáticamente.

Consulta `DEPLOY.md` para la instalación en Namecheap/cPanel y el subdominio privado.
