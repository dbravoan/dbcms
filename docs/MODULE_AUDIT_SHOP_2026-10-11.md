# Auditoría funcional Shop, BeforeAfter y Charts — 2026-10-11

Entorno local Sail, base `laravel`, con copia previa realizada por el orquestador. Las peticiones MCP usaron OAuth del administrador existente, cliente propio y tokens temporales mantenidos válidos hasta las aprobaciones humanas en la UI. Todos esos tokens/clientes quedaron revocados. No se ejecutaron migraciones, seeders ni pruebas destructivas sobre esta base. Los datos QA se conservan para revisión.

## Inventario QA conservado

| Entidad | ID | Resultado |
| --- | --- | --- |
| Categorías Shop exploratorias | 1, 2 | Borradores creados antes de corregir un fallo del harness de registro; conservados |
| Categoría Shop principal | 3 | Creación y edición MCP; publicación aprobada en UI; activa |
| Producto `QA-20261011-SHOP` | 1 | Publicado por revisión humana; mercado ES 1; precio base 12,50; precio específico ES **0**; stock inicial 3, final 1 |
| Mercado `QA-20261011 UK` | 2 | Creado por MCP y aprobado en UI; GBP, idioma EN existente 2 |
| Pedido transferencia | 1 / `ORD-JQEASQDODUMVY3UT` | `bank_transfer`, `pending`, `unpaid`, EUR, total 0 |
| Pedido contra reembolso | 2 / `ORD-Q9YFT8ALUGDV9EJI` | `cod`, `pending`, `unpaid`, EUR, total 0 |
| Consulta de presupuesto | 1 | Producto 1, mercado ES 1, idioma ES; persistida y marcada revisada por MCP |

Producto público: `/es/es/tienda/qa-20261011-shop-producto`. Administración: `/control/shop/products/1/edit`, `/control/shop/orders/1`, `/control/shop/orders/2`, `/control/shop/inquiries`. El primer harness de checkout se detuvo **después** de crear el pedido 1 por consultar `total` en vez de `grand_total`; se corrigió y se continuó solamente con COD, sin duplicar transferencia.

## Comprobaciones reales

Peticiones HTTP con sesión/cookies independientes y CSRF de la sesión, contra el servidor Sail. Creación/edición/revisión por MCP auténtico; publicación mediante aprobación humana del orquestador en el backoffice.

| Caso | Evidencia |
| --- | --- |
| Producto ES publicado | GET 200 |
| Producto ES consultado en UK | GET 404 |
| Carrito con cantidad 1 | POST 200; lectura count 1 y total 0, respetando `custom_price=0` |
| Cantidad superior al stock / cantidad negativa | 422 / 422 |
| Misma sesión de carrito bajo `X-Market: uk` | 422; no se consumen productos de otro mercado |
| Checkout con tarjeta | 422; ningún pedido con tarjeta creado |
| Transferencia y COD | 302 a página firmada; GET firmado 200; stock decrece una unidad por pedido |
| Pedido desde otra sesión sin firma | 404 |
| Pedido de ES desde URL UK | 404 |
| Consulta de presupuesto ES / UK | 302 y fila persistida / 404 sin nueva consulta |
| Notificación | Mailpit local contiene un mensaje «Nueva solicitud de presupuesto»; no salida SMTP externa |
| Estados MCP `shipped` / `simulated_paid` | Rechazados; pedido conserva `pending/unpaid` |
| Informe de pagos históricos | Consulta de solo lectura para `card/stripe` pagados: 0 filas en este entorno |

No se simularon pagos ni se marcaron pedidos como pagados. No se ha validado una pasarela de pago, entrega de correo externa o una compra GBP. La concurrencia del stock cuenta con una integración MySQL previa separada; esta ronda funcional ejecutó dos compras consecutivas.

## Correcciones y contratos de widgets

Se alinearon los enums de estado del recurso Order con el backoffice, incluyendo el rechazo de `null`. Regresión sin DB: `ShopOrderStatusValidationTest`, 9 pruebas verdes; Pint y PHPStan del archivo, verdes.

Charts declara bloques `bar`, `donut`, `line` y `metric`, además del widget `charts.kpi_counters`. Se reprodujo que la modalidad `line` ignoraba las etiquetas `label` generadas por su propio editor y emitía los extremos `point` sin escape. El renderer ahora usa `label` con compatibilidad `point` y escape HTML. RED 2 → GREEN 2 pruebas / 5 aserciones (`ChartLinePayloadTest`); Pint y PHPStan del renderer, verdes.

BeforeAfter declara el widget `before_after.comparison_slider` con imágenes antes/después y etiquetas; Charts y BeforeAfter son módulos de bloques/widgets, sin CRUD de entidades propias. Esta auditoría de contratos no certifica interacción de arrastre/táctil ni accesibilidad visual en navegador.

Se ejecutó render real de BeforeAfter con rutas locales de imagen y etiquetas QA: HTML contiene ambas rutas, etiquetas y control range. Los cuatro modos Charts renderizaron HTML no vacío en el runtime. No se descargaron imágenes externas.

El formulario web de producto conservaba idiomas inactivos mediante claves planas `title_*`, `slug_*`, `summary_*` que el nuevo FormRequest ignoraba. Se cambiaron a `translations[locale][name/slug/summary/description]`. RED → GREEN de render Blade y validación de la carga multilingüe: 1 prueba / 8 aserciones. Las categorías Shop ya envían nombres anidados para todos los idiomas.

Las categorías Shop públicas tampoco aplicaban la asignación de mercados. Se añadió el scope común de mercados al modelo y a los listados, filtros y resolución de categorías. Las categorías sin asignación conservan su comportamiento global. Regresión SQLite aislada: 3 pruebas verdes. Comprobación HTTP: el listado ES devuelve 200 e incluye la categoría QA de ES; UK devuelve 200 y la excluye.

Se creó la página QA **3**, `/es/es/qa-20261011-widgets` y `/es/en/qa-20261011-widgets`, mediante MCP, con KPI, BeforeAfter y gráfico de líneas localizados. La primera aprobación reprodujo un defecto de paridad: página publicada con tres secciones todavía `draft`, por lo que ambos GET devolvían 200 sin bloques. Publicar sin reenviar `sections_json` ahora promueve la versión existente mediante `SectionSyncService`, compartido por el escritor de entidades y la aprobación de ContentAudit. Se conservan IDs y traducciones; las secciones de páginas que siguen privadas no se exponen. RED → GREEN: `SectionPublicationParityTest`, 2 pruebas / 5 aserciones.

La nueva ejecución `/control/executions/ea5db666-609d-4f8b-bf00-78bc9d607c44`, aprobada en UI, terminó `completed` y las tres secciones quedaron `published`. Los GET ES y EN devuelven **200** y contienen el KPI en su idioma, ambas imágenes BeforeAfter, el control `input type="range"` y las etiquetas QA A/QA B del gráfico. Los PNG locales `/storage/qa-20261011/widgets-before.png` y `widgets-after.png` son fixtures de 64×64 y devuelven **200 image/png**. Se revocó el último cliente/token de widgets tras verificar estos resultados. La interacción visual/teclado queda a la comprobación de navegador del orquestador.

Evidencia detallada local, permisos 600: `storage/app/runtime-verification/module-audit-20261011/shop-evidence.json` y `shop-front-evidence.json`. No incluyen credenciales OAuth ni firmas de acceso a pedidos. Las antiguas solicitudes pendientes emitidas con tokens ya revocados no deben aprobarse.
