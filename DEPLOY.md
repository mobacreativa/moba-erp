# Despliegue en Namecheap / cPanel

Destino: `erp.mobacreativa.com`. No se ha desplegado todavía.

1. Subir el proyecto a una carpeta privada de la cuenta, por ejemplo `~/moba-erp`. No subir `.git`, `tests` ni `.github` al servidor.
2. En Domains crear `erp.mobacreativa.com` con raíz `moba-erp/public`, sin compartir la raíz de la web principal. Si cPanel obliga a usar `public_html`, verificar que la raíz del proyecto queda bloqueada por `.htaccess` y solo `public/` es accesible.
3. Comprobar dónde se gestiona el DNS antes de añadir el registro del subdominio. Mantener los registros de la web y del correo existentes.
4. Activar el certificado SSL del subdominio y verificarlo antes de iniciar sesión. La aplicación exige HTTPS en producción.
5. Seleccionar PHP 8.3 o posterior y activar PDO MySQL. Desactivar `display_errors` en producción y mantener logs fuera de la raíz pública.
6. Crear base de datos y usuario dedicado; conceder acceso únicamente a esa base. Importar `database/schema.sql` una sola vez en la base vacía con phpMyAdmin.
7. Copiar `config/local.example.php` a `config/local.php`. Completar el DSN, usuario y contraseña de base de datos, `admin_username` y `admin_password_hash`. Mantener `secure_cookie` en `true`. Usar permisos 600 para el archivo privado si el hosting lo admite.
8. Generar el hash de administrador con `php bin/password.php` desde Terminal de cPanel. Introducir la contraseña allí, no en GitHub ni en el chat. No existe contraseña predeterminada ni alta pública.
9. Verificar HTTPS, login incorrecto/correcto, cierre de sesión, alta y edición de un cliente de prueba, y que `/config/local.php`, `/database/schema.sql` y `/.git/config` no son accesibles.
10. Configurar y comprobar restauración de copias de seguridad antes de introducir datos reales.

Las credenciales y los datos reales nunca se añaden al repositorio. Para futuras actualizaciones, conservar `config/local.php` y la base de datos. No volver a importar el esquema sobre una base existente.
