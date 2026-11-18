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

## Configuración y Contabilidad · 16 noviembre 2026
Desplegado moba-accounting-20261115.zip en /home/mobaewow/moba-erp (13 archivos app/public). Extracción completa confirmada en cPanel y acceso HTTPS comprobado sin autenticar. Copia previa: backup-before-accounting-20261115.zip (app/public) fuera de public. No se han modificado configuración privada, datos ni esquema.

Código probado: 0d3dd76c9f7ce35915db0cae3feeb9de3642d443, rama codex/erp-budget-workflow. GitHub Actions 43762747091: PHP 8.2 y 8.3, pruebas API/DB y navegador aprobadas. Incluye agrupación Configuración general, Contabilidad para administrador, registro manual EUR, cobros/pagos, calendario, adaptador opcional Azure Document Intelligence y corrección de accesibilidad de selectores de tareas.

Pendiente: comprobación autenticada en producción (usuario debe iniciar sesión); recurso y credenciales Azure y prueba real OCR. El usuario autorizó preparar Azure Document Intelligence el 16 noviembre. El lector sigue desactivado, no se han transmitido facturas a Azure. Se ha abierto portal.azure.com para que el usuario inicie sesión.

## Contabilidad · 17 noviembre 2026
Desplegado y extracción confirmada en cPanel: work/moba-accounting-20261117.zip (11 archivos, 28.392 bytes) a /home/mobaewow/moba-erp. Copia previa confirmada backup-before-accounting-20261117.zip (10,61 MB, app/public). Conservadas configuración, documentos y DB. Captura outputs/accounting-deploy-20261117.jpg.

Commit probado 08c656bcb6a104f86470634f82f126b402b98500, rama codex/erp-budget-workflow. CI 44023717832 aprobada PHP 8.2/8.3, DB, HTTP, navegador, OCR local real y flujo Azure simulado. Incluye Clientes/Proveedores en Contabilidad, conservación de acceso comercial a contactos sin datos financieros, encabezado calendario con mes/año, eliminar/restaurar movimientos y exclusión de borrados en métricas/calendario. Azure es el lector principal y local alternativa explícita. Configuración privada separada opcional config/invoice-reader.php (no creada, no versionar).

Azure: sesión abierta, directorio actual muestra cero suscripciones. Abierto formulario de prueba gratuita Azure; usuario debe aceptar contrato y completar alta. No hay recurso ni clave configurados, ninguna factura enviada a Azure. Comprobación autenticada del despliegue pendiente: abierta pestaña ERP solicitando login al usuario. Mantener los cambios ya publicados mientras se completa alta, no afirmar Azure activo.

## Azure conectado y verificado · 17 noviembre 2026
Actualiza el estado pendiente anterior: recurso moba-erp-ocr activo en North Europe, tarifa F0. Desplegado moba-azure-20261117.zip (6 archivos); commit 6be8c51521eac6be18f6fd2f78a3fd266513622e, CI 44072577125 aprobada PHP 8.2/8.3. Usuario guardó personalmente la clave mediante el formulario privado; no se leyó ni versionó la credencial.
Prueba real desde ERP contra Azure completada: factura-ficticia-ocr.png detectó Taller Demo SL, PRUEBA-LOCAL, fecha 2026-11-16, base 100 EUR, IVA 21 EUR, total 121 EUR y vencimiento 2026-12-16. Azure señaló baja confianza en algunos campos; revisión humana conservada. Se canceló el formulario SIN registrar gasto: métricas y movimiento real intactos. El archivo ficticio permanece en Facturas pendientes de registrar, no contabilizar. Captura outputs/azure-ocr-verified-20261117.jpg.
F0 limita cada archivo a 4 MB y primeras dos páginas. Clientes/Proveedores, mes del calendario y botones Eliminar/Papelera comprobados en sesión autenticada. No afirmar finalizado el resto del alcance original.
