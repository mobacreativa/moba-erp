# Despliegue en Namecheap / cPanel

Destino: `erp.mobacreativa.com`. No se ha desplegado todavía.

1. En cPanel > Git Version Control, pulsar Create y clonar `https://github.com/mobacreativa/moba-erp` en una carpeta privada, por ejemplo `repositories/moba-erp` (no dentro de `public_html`). Después, en Manage > Pull or Deploy, pulsar Deploy HEAD Commit. El archivo `.cpanel.yml` copia solo `app`, `bin`, `database`, `public`, `config/local.example.php` y `.htaccess` a `~/moba-erp`; no copia `.git`, `tests` ni `.github`.
2. En Domains crear `erp.mobacreativa.com` con raíz `moba-erp/public`, sin compartir la raíz de la web principal. Si cPanel obliga a usar `public_html`, verificar que la raíz del proyecto queda bloqueada por `.htaccess` y solo `public/` es accesible.
3. Comprobar dónde se gestiona el DNS antes de añadir el registro del subdominio. Mantener los registros de la web y del correo existentes.
4. Activar el certificado SSL del subdominio y verificarlo antes de iniciar sesión. La aplicación exige HTTPS en producción.
5. Seleccionar PHP 8.3 o posterior y activar PDO MySQL. Desactivar `display_errors` en producción y mantener logs fuera de la raíz pública.
6. Crear base de datos y usuario dedicado; conceder acceso únicamente a esa base. Importar `database/schema.sql` una sola vez en la base vacía con phpMyAdmin.
7. Copiar `config/local.example.php` a `config/local.php`. Completar el DSN, usuario y contraseña de base de datos, `admin_username` y `admin_password_hash`. Mantener `secure_cookie` en `true`. Usar permisos 600 para el archivo privado si el hosting lo admite.
8. Generar el hash de administrador con `php bin/password.php` desde Terminal de cPanel. Introducir la contraseña allí, no en GitHub ni en el chat. No existe contraseña predeterminada ni alta pública.
9. Verificar HTTPS, login incorrecto/correcto, cierre de sesión, alta y edición de un cliente de prueba, y que `/config/local.php`, `/database/schema.sql` y `/.git/config` no son accesibles.
10. Configurar y comprobar restauración de copias de seguridad antes de introducir datos reales.

Las credenciales y los datos reales nunca se añaden al repositorio. No volver a importar el esquema sobre una base existente.

## Actualizaciones

1. Los cambios entran en `main` solo mediante pull request con la CI en verde.
2. El workflow `Deploy` (`.github/workflows/deploy.yml`) se ejecuta cuando la CI de `main` pasa: pide a cPanel Update from Remote y Deploy HEAD Commit, y comprueba que la web responde. También se puede lanzar a mano desde Actions > Deploy > Run workflow. Sin el workflow configurado, hacer esos dos pasos a mano en cPanel > Git Version Control > Manage > Pull or Deploy.
3. El despliegue no toca `config/local.php` ni la base de datos. Si un cambio incluye migraciones de base de datos, aplicarlas aparte siguiendo las instrucciones del pull request, con copia de seguridad previa.
4. El despliegue copia y sobrescribe, pero no borra: si un cambio elimina o renombra archivos, borrar en el servidor las versiones antiguas a mano.
5. Para volver a una versión anterior, desplegar ese commit desde cPanel o revertirlo en GitHub y desplegar de nuevo.

## Configuración del despliegue automático

1. En cPanel > Manage API Tokens, crear un token llamado `github-deploy`, sin caducidad o con una fecha anotada para renovarlo. El token da acceso completo a la cuenta de cPanel: no compartirlo ni pegarlo en el chat.
2. En GitHub > Settings > Environments, crear el entorno `production` y, en Deployment branches, limitarlo a `main`.
3. En ese entorno, añadir los secretos `CPANEL_HOST` (servidor de cPanel, por ejemplo el que aparece en la URL de acceso, sin `https://` ni puerto), `CPANEL_USER` (usuario de cPanel) y `CPANEL_TOKEN` (el token).
4. Si el repositorio no está clonado en `/home/<usuario>/repositories/moba-erp`, añadir la variable `CPANEL_REPO_ROOT` con la ruta correcta.
5. Lanzar Actions > Deploy > Run workflow y comprobar que termina en verde.

Si falla con un error de conexión al puerto 2083, el hosting puede estar bloqueando las IP de GitHub. En ese caso, consultar con soporte de Namecheap o volver al despliegue manual.
