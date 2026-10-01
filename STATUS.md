# Estado del desarrollo · 1 de octubre de 2026

Se recuperó el traspaso del chat «Diseñar ERP presupuestos»: repositorio vacío, PHP y MariaDB/MySQL, marcas y proveedores separados, colores y tallas, costes históricos y futuro motor textil/DTF.

Implementado localmente:
- Acceso de administrador con hash, renovación de sesión, caducidad por inactividad y límite persistente de intentos por IP.
- Formularios con CSRF, salida HTML escapada y consultas parametrizadas.
- Alta, listado, búsqueda y edición de clientes.
- Esquema inicial de catálogo con integridad de marca/color y costes fechados por proveedor.
- Pruebas de validación y persistencia y workflow para MariaDB.

Verificación pendiente: no hay PHP ni MariaDB disponibles en este equipo. Las pruebas no se han ejecutado. El workflow tampoco se ha ejecutado porque no se pudo subir el código.

GitHub: el repositorio se pudo consultar y no tenía ramas. La creación del README por la integración devolvió 403 «Resource not accessible by integration». No se modificó GitHub, no hay PR y no se ha desplegado el ERP.

Próxima sesión:
1. Habilitar escritura de Contents en la integración de GitHub o usar una sesión Git autenticada.
2. Ejecutar las pruebas PHP/MariaDB y una comprobación del flujo web completo antes de publicar.
3. Subir la base local al repositorio y comprobar CI.
4. Recuperar el resumen funcional detallado anterior. No fijar tarifas ni fórmulas DTF/márgenes sin él.
5. Completar interfaz de catálogo y presupuestos con instantáneas históricas.
