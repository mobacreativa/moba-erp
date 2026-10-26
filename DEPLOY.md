# Despliegue en Namecheap / cPanel

Destino exclusivo: erp.mobacreativa.com. La versión inicial de clientes ya funciona con HTTPS y acceso privado.

## Actualización del catálogo y presupuestos

1. Exportar la base mobaewow_erp con phpMyAdmin y guardar una copia privada de app/ y public/ fuera de public/.
2. Importar database/002_erp.sql. Es aditiva e idempotente; no reemplaza clientes ni tablas existentes.
3. Importar por separado el catálogo privado recuperado del prototipo. No incluir este SQL en el repositorio público ni dentro de public/.
4. Subir app/ y public/ de la versión probada. Conservar config/local.php, sus permisos y el DocumentRoot existente /home/mobaewow/moba-erp/public.
5. Comprobar login, clientes existentes, /?view=erp, catálogo, cálculo, guardado y PDF. Las pruebas destructivas se ejecutan solo en CI con datos ficticios.
6. Mantener la copia anterior para volver a sus archivos si hay un fallo. La migración aditiva permite volver al módulo inicial sin borrar datos nuevos.

No cambiar la raíz de mobacreativa.com, sus DNS, correo ni configuración PHP global. No volver a importar schema.sql sobre una base existente. No leer ni publicar credenciales. El repositorio no despliega automáticamente.

Para usuarios y órdenes importar también database/003_users.sql antes de actualizar el código. No modifica credenciales existentes ni crea cuentas.
