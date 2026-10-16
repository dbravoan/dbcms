# Verificación de orquestación nativa y auditoría

> Historical evidence. This records the behavior and checks of its stated date; current contracts live in [Core and extensions](../../specs/CORE_AND_EXTENSIONS.md) and [Testing](../TESTING.md).

Fecha: 2026-10-10. Estado: implementación y verificación integrada completadas.

La implementación mantiene el CMS PHP/Laravel como motor. La entrada de instrucciones permanece desactivada por defecto. Esta entrega no conecta cuentas reales, no publica contenido, no despliega y no concilia pagos históricos automáticamente.

## Cobertura implementada

| Paso | Implementación y evidencia principal |
| --- | --- |
| 1. Contratos y regresiones | `specs/native_cms_orchestration_spec.md`, DTOs readonly, validación de planes y pruebas de paridad. |
| 2. Identidad y autorización | Política común RBAC + OAuth, clientes M2M con rol explícito, revocación y aislamiento MCP/SSE; `CmsSecurityIsolationTest`, `McpPassportM2mTest`, `M2mCliProvisioningTest`. |
| 3. Proveedores y medios | Configuración cifrada, selección explícita de modalidad/modelo, contratos HTTP simulados, seguimiento remoto y subida/importación centralizada; pruebas AI/Media y `StorageCredentialBoundaryTest`. |
| 4. Dominio | Pago manual pendiente, stock bloqueado, pedidos privados y presupuestos persistidos; `ShopSafetyRegressionTest`, `ShopMarketPriceRegressionTest`, `ShopStockConcurrencyTest`. |
| 5. Catálogo y ejecutor | `App/Core/Capabilities`, adaptadores MCP compatibles, registros propios de módulos y servicios de entidades/recursos compartidos; `CapabilityArchitectureTest`, `ModuleCapabilityOwnershipTest`, pruebas Editorial/Shop/Gallery de paridad. |
| 6. Disponibilidad | Catálogo filtrado por identidad, módulos, configuración/modalidad y permisos; comprobación repetida al ejecutar. `ModuleExecutionIndependenceTest` verifica IA con MCP desactivado y MCP concreto con IA desactivada. |
| 7. Planificador | `CmsNativePlanner`: modelo de texto configurado, máximo 20 pasos, una revisión automática, aclaraciones y validación de argumentos/dependencias. |
| 8. Estado y confirmaciones | Ejecuciones/pasos/aprobaciones persistidos, vínculo con actor y versión, checkpoints, incertidumbre y cancelación; `NativeExecutionTest`, `AiGenerationAsyncTest`. |
| 9. Interfaces | Entrada de instrucciones y revisión común en ambos temas administrativos; API/MCP para iniciar, consultar, responder, reanudar y cancelar. Aprobación únicamente en sesión web con CSRF. |
| 10. Caché y mercados | HTML público interno con hidratación privada por petición, exclusión de páginas sensibles y CAPTCHA, invalidación de listados/índices tras commit; pruebas PageCache/Market y `ContentAuditCacheInvalidationTest`. |
| 11. Módulos y arquitectura | Desinstalación conserva datos, migraciones pendientes al reinstalar, módulos desactivados fuera del catálogo; PHPStan nivel 8 sin baseline y pruebas Architecture. |
| 12. Entrega y documentación | Calidad y builds en Docker, secretos excluidos, salud obligatoria, recuperación de código sin revertir datos, runbooks y compatibilidad. |

## Verificación integrada

La ejecución final en la imagen CI separada verifica el mismo código y los lockfiles de la entrega:

| Comprobación | Resultado |
| --- | --- |
| Pest completo | 1.092 pruebas correctas, 9.377 aserciones, 497,99 segundos; salida 0. |
| PHPStan nivel 8 | 0 errores, 783 archivos analizados; sin baseline, ignores ni exclusiones. |
| Pint | 969 archivos correctos. |
| Composer | Validación estricta e instalación correctas; auditoría sin avisos de vulnerabilidad. |
| npm | Instalación reproducible y auditoría sin vulnerabilidades. |
| Vite | Build correcto y manifiesto presente. |
| Imagen runtime | Build correcto; arranque real con configuración, rutas y vistas cacheadas. |
| HTTP runtime | `/up` 200, archivo PHP realmente subido 404, `/.env` 403; Nginx válido. |
| Secretos del artefacto | Configuración privada excluida, clave Passport privada 600 y pública 644. |
| Documentación final | 3 pruebas correctas: scripts, comandos Artisan e índice de documentos. |

La imagen runtime comprobada es `dbcms:final-runtime` (`sha256:0906dd50fff0b3b2e1c6fe2ef0c142e4d6680c4a604e464b0cfd40c365faddeb`). Estas verificaciones se ejecutaron en contenedores locales desechables, sin desplegar ni usar cuentas reales.

La verificación integrada usa MySQL y Redis separados de Sail y no necesita credenciales de proveedores. Se reproduce desde la raíz del proyecto:

```bash
docker compose -p dbcms-final-verify -f docker/ci/compose.yml run --build --rm app bash bin/ci-check
docker build --target runtime -f docker/production/Dockerfile -t dbcms:final-runtime .
docker compose -p dbcms-final-verify -f docker/ci/compose.yml down --volumes --remove-orphans
```

El último comando elimina exclusivamente los volúmenes de ese proyecto de pruebas. No se debe ejecutar con el nombre del entorno de desarrollo o producción. La suite utiliza un idioma explícito y suficiente memoria para el análisis de arquitectura; la imagen desactiva etiquetas cortas PHP. Los editores también evitan aperturas PHP literales dentro de sus expresiones JavaScript.

Las regresiones de integración comprobaron dos fallos antes de corregirlos: el editor no compilaba con `short_open_tag=1`, y una ruta de búsqueda aceptaba un idioma activo sin asociación al mercado. El editor corregido compila con ambas configuraciones PHP y su expresión JavaScript conserva la equivalencia en 13 entradas; la ruta incompatible devuelve 404. Búsqueda, todos los temas e instalación sin módulos superaron después 31 pruebas con 492 aserciones. Las pruebas de instalación usan copias verificadas para aislar directorios, porque renombrar directorios de una capa Docker puede fallar con `EXDEV`.

La prueba concurrente ejecuta dos procesos Laravel y dos conexiones MySQL contra el último producto disponible. Comprueba que ambos `SELECT FOR UPDATE` esperan por la misma fila, y exige exactamente un pedido creado, uno rechazado y stock cero. No utiliza transacciones de prueba envolventes ni la base de datos de desarrollo.

## Límites de la evidencia y activación

Las pruebas contractuales de proveedores emplean HTTP simulado y credenciales ficticias. Una clave guardada no acredita acceso real ni disponibilidad del modelo. Verificar cada conexión en staging antes de habilitarla.

La entrada de instrucciones exige configurar un modelo de texto y activar `CMS_ORCHESTRATION_ENABLED`. Las herramientas concretas conservan acceso sin modelo ni entrada de instrucciones. Las cuentas y aplicaciones nuevas no se habilitan por suposición.

Antes de una release, seguir `NATIVE_ORCHESTRATION_RUNBOOK.md`: backup verificable, migraciones compatibles, revisión expresa de M2M/permisos, validación de staging y conciliación del informe de pagos simulados. La recuperación de código conserva los datos y migraciones aditivas. Ninguna prueba local equivale a desplegar.
