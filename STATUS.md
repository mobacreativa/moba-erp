# Estado · 4 de octubre de 2026

Instalada en producción la versión 3bc00100c394e89a24325e63290101840931f602: catálogo, tarifas, presupuestos, usuarios con roles y órdenes de trabajo asignables. Se verificó la extracción de app/ y public/ en /home/mobaewow/moba-erp. Configuración privada conservada. La página de acceso responde por HTTPS; falta la comprobación autenticada con el administrador real.

Pruebas aprobadas: GitHub Actions 37113953903, PHP 8.2/8.3, MariaDB, API y navegador. Incluyen aislamiento de órdenes, permisos, invalidación de sesiones y flujo de crear usuario/asignar/terminar trabajo.

Base de datos: migraciones 002 y 003 aplicadas el 3 de octubre. Verificadas el 4: erp_records y erp_history con 200 registros cada una, erp_users vacía (no se crean credenciales reales automáticamente). Catálogo privado importado: 96 productos, 20 tarifas, 36 colores, 16 tallas y proveedores.

Respaldo: SQL local privado work/backup-before-team-20261003.sql; archivos previos fuera del directorio público, backup-app-20261003.zip y backup-public-20261004.zip en /home/mobaewow/moba-erp.

Pendientes del alcance original: importación XLSX directa, ofertas múltiples por producto, plantillas reutilizables y configuradores especializados. Facturación/cobros/almacén eran fases futuras no especificadas. No presentar el ERP completo como finalizado.

## Ajuste de entrada (4 octubre)
Desplegado moba-home-fix.zip: la raíz autenticada abre Inicio; enlaces Clientes apuntan a ?view=customers. Producción conserva órdenes. Verificación visual autenticada pendiente por sesión caducada.

## Menú y equipo · 23 octubre 2026
Preparado, pendiente de despliegue por falta de sesión cPanel: menú homogéneo font-weight 300; Nuevo presupuesto solo dentro de Presupuestos; acceso directo Crear usuario en órdenes para administrador. Roles de producción ya restringidos en servidor a órdenes propias; pantalla Usuarios verificada en producción con administrador. JS validado con node --check. Paquete work/moba-menu-20261023.zip (4 archivos, sin datos ni configuración).

Desplegado y verificado el 23 octubre: paquetes moba-menu-20261023.zip y moba-menu-assets-20261023.zip. Comprobados menú light (peso calculado 300), formulario Crear usuario con Producción predeterminado, y Nuevo presupuesto dentro de Presupuestos. Recursos versionados para invalidar caché. No se han creado usuarios reales ni modificado datos. Captura outputs/menu-20261023.png.

24 octubre: desplegado moba-workflow-20261024.zip. Flujo de trabajo renombrado, Usuarios separado en menú principal (admin), filtros combinables cliente/estado/responsable y limpiar. Cliente seleccionable en orden; presupuesto prevalece. Verificados filtros en producción y acceso directo Usuarios, sin modificar órdenes reales. JS syntax y combinaciones probadas con Node. Órdenes antiguas sin cliente muestran Sin cliente. Captura outputs/workflow-20261024.png.
