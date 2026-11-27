# Despliegue en Namecheap / cPanel

Destino exclusivo: `erp.mobacreativa.com`. La aplicación ya funciona con HTTPS y acceso privado.

Instalación actual: el repositorio está clonado con cPanel > Git Version Control en `/home/mobaewow/moba-erp`, que es también la carpeta de la web (DocumentRoot del subdominio: `/home/mobaewow/moba-erp/public`). `.git`, `tests` y `.github` quedan fuera de `public/` y no son accesibles desde la web. `config/local.php`, `config/invoice-reader.php`, `storage/` y `public/vendor/` están en `.gitignore`, así que las actualizaciones no los tocan. No editar en el servidor archivos versionados: si hay cambios locales, cPanel no podrá actualizar.

No cambiar la raíz de mobacreativa.com, sus DNS, correo ni configuración PHP global. No leer ni publicar credenciales. Las credenciales y los datos reales nunca se añaden al repositorio.

## Actualizaciones

1. Los cambios entran en `main` solo mediante pull request con la CI en verde.
2. Si el pull request incluye migraciones (`database/0XX_*.sql`), antes de fusionarlo exportar la base `mobaewow_erp` con phpMyAdmin e importar las migraciones nuevas en orden. Deben ser aditivas e idempotentes. No volver a importar `schema.sql` sobre una base existente.
3. Al fusionar, el workflow `Deploy` (`.github/workflows/deploy.yml`) se ejecuta cuando la CI de `main` pasa: pide a cPanel Update from Remote, que en esta instalación ya actualiza la web, y comprueba que responde. También se puede lanzar a mano desde Actions > Deploy > Run workflow, o hacer Update from Remote en cPanel > Git Version Control > Manage > Pull or Deploy.
4. Comprobar login, clientes existentes, `/?view=erp`, catálogo, cálculo, guardado y PDF. Las pruebas destructivas se ejecutan solo en CI con datos ficticios.
5. Para volver a una versión anterior, revertir el pull request en GitHub y dejar que se despliegue. Las migraciones aditivas permiten volver al código anterior sin borrar datos nuevos.

## Configuración del despliegue automático

1. En cPanel > Manage API Tokens, crear un token llamado `github-deploy`, sin caducidad o con una fecha anotada para renovarlo. El token da acceso completo a la cuenta de cPanel: no compartirlo ni pegarlo en el chat.
2. En GitHub > Settings > Environments, crear el entorno `production` y, en Deployment branches, limitarlo a `main`.
3. En ese entorno, añadir los secretos `CPANEL_HOST` (servidor de cPanel, sin `https://` ni puerto), `CPANEL_USER` (usuario de cPanel) y `CPANEL_TOKEN` (el token), y la variable `CPANEL_REPO_ROOT` con la ruta que muestra cPanel > Git Version Control (`/home/mobaewow/moba-erp`).
4. Lanzar Actions > Deploy > Run workflow y comprobar que termina en verde.

Si falla con un error de conexión al puerto 2083, el hosting puede estar bloqueando las IP de GitHub. En ese caso, consultar con soporte de Namecheap o actualizar a mano desde cPanel.

## Instalación desde cero

Solo para una instalación nueva en otra cuenta.

1. En cPanel > Git Version Control, clonar `https://github.com/mobacreativa/moba-erp`. Si se clona en la carpeta de la web (por ejemplo `moba-erp`), basta con Update from Remote. Si se clona en una carpeta separada (por ejemplo `repositories/moba-erp`), Deploy HEAD Commit usa `.cpanel.yml` para copiar a `~/moba-erp` solo `app`, `bin`, `database`, `public`, `config/local.example.php` y `.htaccess`; ese despliegue copia y sobrescribe, pero no borra archivos eliminados.
2. En Domains crear `erp.mobacreativa.com` con raíz `moba-erp/public`, sin compartir la raíz de la web principal. Mantener los registros DNS de la web y del correo.
3. Activar el certificado SSL del subdominio. La aplicación exige HTTPS en producción.
4. Seleccionar PHP 8.3 o posterior con PDO MySQL. Desactivar `display_errors` y mantener logs fuera de la raíz pública.
5. Crear base de datos y usuario dedicado con acceso solo a esa base. Importar `database/schema.sql` una sola vez en la base vacía y después las migraciones `database/0XX_*.sql` en orden.
6. Copiar `config/local.example.php` a `config/local.php` y completarlo. Mantener `secure_cookie` en `true` y permisos 600 si el hosting lo admite.
7. Generar el hash de administrador con `php bin/password.php` desde Terminal de cPanel. Introducir la contraseña allí, no en GitHub ni en el chat.
8. Verificar HTTPS, login incorrecto/correcto, cierre de sesión, alta y edición de un cliente de prueba, y que `/config/local.php`, `/database/schema.sql` y `/.git/config` no son accesibles.
9. Configurar y comprobar restauración de copias de seguridad antes de introducir datos reales.

## Pedidos de Shopify

Cada pedido nuevo de la tienda (evento «Creación de pedido») crea una orden de trabajo con Fuente «Web (Shopify)»: productos con variante y SKU, cantidades, personalizaciones, nota del cliente y dirección de envío, sin precios. El cliente se vincula por email o se crea en Clientes. Un mismo pedido nunca crea dos órdenes. Los cambios o cancelaciones posteriores en Shopify no se sincronizan: se gestionan en la orden.

1. En el ERP, Configuración general > Usuarios: elegir o crear el usuario que recibirá los pedidos (rol Producción o Administrador, activo). El administrador principal de `config/local.php` no puede ser responsable de órdenes.
2. En Shopify, Configuración > Notificaciones > Webhooks > Crear webhook: evento «Creación de pedido», formato JSON, URL `https://erp.mobacreativa.com/?webhook=shopify` y la versión de API más reciente. Copiar la clave con la que Shopify firma los webhooks (aparece en esa misma página).
3. En cPanel > Administrador de archivos, editar `moba-erp/config/local.php` y añadir antes del `];` final, sin compartir la clave en GitHub ni en el chat:

   ```php
   'shopify' => [
       'webhook_secret' => 'CLAVE_DE_FIRMA_DE_SHOPIFY',
       'assignee_username' => 'usuario_del_paso_1',
       'shop_domain' => '',
   ],
   ```

4. En Shopify, pulsar «Enviar notificación de prueba» junto al webhook. Debe aparecer en Flujo de trabajo una orden «Shopify #…» de prueba; después eliminarla con «Eliminar orden».

Si la clave no coincide, el ERP rechaza el aviso (401). Si falta la configuración o el usuario no es válido, responde con error y Shopify reintenta el envío durante unas horas; el motivo queda en el log de errores de PHP.

## Avisos por correo

Cuando alguien asigna (o reasigna) desde el ERP una orden de trabajo o una tarea general a otro usuario, este recibe un correo desde `noreply@mobacreativa.com` con el título, la fecha de entrega estimada, los productos (sin precios ni datos del cliente) y el enlace al ERP. No se envía si uno se asigna algo a sí mismo ni para las órdenes que crea Shopify. Si el envío falla, la orden o tarea se guarda igualmente y el motivo queda en el log de PHP.

1. Antes de usarlo, exportar la base de datos con phpMyAdmin e importar `database/004_user_email.sql` (añade la columna `email` a los usuarios; se puede importar más de una vez). Hasta entonces el ERP funciona igual, pero no deja guardar correos.
2. En Configuración general > Usuarios, añadir el correo electrónico de cada usuario.
3. Opcional, en `config/local.php`: `'admin_email' => 'correo@mobacreativa.com',` para que el administrador principal también reciba avisos de tareas; y `'mail' => ['enabled' => false],` para desactivar todos los envíos.

Los correos salen con `mail()` del hosting, que firma con el SPF y DKIM del dominio en cPanel. Si alguno llega a spam, revisar en cPanel > Email Deliverability que mobacreativa.com aparece como válido.
