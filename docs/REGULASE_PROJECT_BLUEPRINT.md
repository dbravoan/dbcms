# RegulaSE — propuesta del primer proyecto

Estado: definición editorial y técnica, 12 de octubre de 2026. No hay aún repositorio
derivado, dominio adquirido, tema propio ni contenido de RegulaSE publicado.

## Propósito y posición editorial

RegulaSE explicará qué protegen las exigencias de seguridad, calidad y derechos
en los productos que compramos, cuánto cuesta cumplirlas y cuándo la burocracia
puede ser redundante o perjudicial. Empezará en España con vocación europea.
Su posición será exigir competencia bajo reglas verificables y que ahorrar no
signifique aceptar riesgos o abusos que rechazaríamos en nuestra propia industria.

La tesis «la hiperburocratización ha matado nuestra industria» se tratará como una
hipótesis y una posición editorial para contrastar por sectores, períodos y países.
Una pieza de opinión puede defenderla con argumentos; una ficha explicativa no la
presentará como causalidad demostrada sin datos. Analizar también energía, productividad,
inversión, comercio, tecnología y empleo evita atribuir todos los cambios a una causa.
[Eurostat SBS](https://ec.europa.eu/eurostat/web/structural-business-statistics)
ofrece datos empresariales comparables; sus cifras descriptivas no demuestran por sí
solas una relación causal.

No se presumirá que un producto importado incumple por su origen. La legislación
de producto aplicable exige conformidad para vender en la UE, con obligaciones de
importadores y distribuidores. Distinguir incumplimiento detectado, falta de información
y simple procedencia extranjera.
[Comisión Europea: conformidad de productos](https://europa.eu/youreurope/business/product-rules-compliance/general-product-compliance/index_en.htm).

El marcado CE tampoco acredita origen europeo ni una aprobación de la Comisión;
no se requiere para todos los productos. No se presentará como un sello universal
de calidad o de condiciones laborales.
[Comisión Europea: CE](https://single-market-economy.ec.europa.eu/single-market/goods/ce-marking_en).
Las obligaciones de acceso al mercado y los derechos laborales territoriales no son
idénticos conceptos. La prohibición específica de productos realizados con trabajo
forzoso se aplica desde el **14 de diciembre de 2027**, a cualquier origen; en octubre
de 2026 esa fecha es futura, no una obligación ya aplicable bajo ese reglamento.
[Comisión Europea: trabajo forzoso](https://single-market-economy.ec.europa.eu/single-market/goods/forced-labour-regulation/what-forced-labour-regulation-and-how-does-it-work_en).

## Estructura inicial

| Sección | Pregunta del lector | Implementación inicial |
|---|---|---|
| Entender la normativa | ¿Qué se exige, a quién y desde cuándo? | Páginas y guías del Blog por categoría |
| Comprar con criterio | ¿Qué puedo comprobar antes de comprar? | Guías y checklists por tipo de producto |
| Calidad y seguridad | ¿Qué riesgo reduce cada obligación? | Explicaciones, ejemplos y glosario |
| Industria y burocracia | ¿Qué costes aportan valor y cuáles dificultan competir? | Análisis con datos y opinión etiquetada |
| Alertas verificadas | ¿Existe una retirada o alerta oficial sobre este producto? | Artículos que enlazan la alerta, sin acusaciones no acreditadas |
| Fuentes y método | ¿Cómo se ha investigado y cuándo se revisó? | Página de metodología y registro de correcciones |
| Participar | ¿Cómo comunicar un error o proponer un tema? | Formulario moderado, sin publicación automática |

Enlaces de referencia: [BOE](https://www.boe.es/),
[EUR-Lex](https://eur-lex.europa.eu/) y
[Safety Gate](https://ec.europa.eu/safety-gate-alerts/screen/webReport).
No republicar íntegramente normas técnicas de pago ni asumir derechos sobre fotografías
o documentos de terceros. Enlazar la edición y el identificador oficial consultados.

Primera entrega propuesta: portada, misión, metodología, contacto, autores,
política de correcciones y páginas legales; después seis guías y dos análisis revisados.
Temas iniciales: qué significa CE, qué responsabilidades tiene un importador,
cómo distinguir obligación legal y norma voluntaria, cómo consultar alertas,
qué información debe acompañar un producto según su categoría, y cómo valorar
costes de conformidad sin confundirlos con trámites redundantes. Investigar cada tema
antes de redactarlo: esta lista no constituye una conclusión jurídica para todos los productos.

## Contrato de una pieza explicativa

Cada pieza incluirá, de manera visible:

1. Una respuesta inicial breve y concreta a su pregunta.
2. Jurisdicción, categoría de producto, destinatario y excepciones relevantes.
3. Estado de la disposición: propuesta, adoptada, en vigor, aplicable, modificada
   o derogada; separar entrada en vigor de fecha de aplicación y de transitorios.
4. Identificadores y enlaces oficiales, artículos relevantes y fecha de consulta.
5. Qué debe hacer el ciudadano o la empresa y qué no puede concluir de la información.
6. Autor, revisor con competencia suficiente, fecha de actualización y correcciones.
7. Preguntas frecuentes útiles, glosario y enlaces internos relacionados.

Usar bloques existentes para una primera versión. Un futuro módulo de fichas
normativas puede tipar esos campos, fuentes, revisiones y relaciones entre normas;
**no está implementado** por documentarlo. Publicar sólo con revisión humana,
especialmente si se afirma incumplimiento, se interpreta una obligación o se compara
un fabricante. No fabricar fuentes, inspecciones, certificados, puntuaciones ni datos.

## SEO, GEO y AEO que se pueden comprobar

Crear URLs estables, enlaces internos útiles y título/descripción específicos.
Publicar contenido visible accesible sin depender de una generación en el navegador.
Configurar autor y organización reales, imágenes con permiso/alt, fecha de revisión,
canonical, sitemap y RSS. El JSON-LD debe describir lo que el lector puede ver:
Article/BlogPosting para guías y análisis; WebPage para páginas según las capacidades
implementadas. No añadir LegalService si no existe ese servicio, ni credenciales o
certificaciones inexistentes en Organization/Person. Un schema válido no garantiza
un resultado enriquecido.

Para respuestas de IA, escribir respuestas claras, precisas, matizadas y con fuentes
comprobables. El corpus GEO se deriva de la versión publicada y enlaza la fuente
canónica. No convertir una opinión en un hecho por aparecer en `/llms-full.txt`.
Google indica que no necesita un archivo especial ni un schema distinto para sus
funciones generativas; `llms.txt` no sustituye la indexabilidad ni mejora por sí mismo
su ranking.
[Google: guía para funciones generativas](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide).

Separar mercado de jurisdicción: un artículo leído desde España puede explicar
normativa UE; una traducción inglesa no cambia su ámbito jurídico. Inicialmente
España/español; ampliar inglés y mercados sólo cuando sus contenidos estén revisados.
Los alternates se publican únicamente para traducciones realmente disponibles.
No generar páginas vacías para todos los países ni traducir automáticamente conceptos
jurídicos y publicar sin revisar su equivalencia.

## IA, seguridad editorial y medición

La IA ayuda a proponer borradores, estructura, metadatos y traducciones. No otorga
aprobaciones ni valida la vigencia normativa. Mantener permisos separados de uso
y configuración; modelos y conexiones disponibles se verifican por actor. Las
publicaciones y acciones externas siguen su confirmación y revisión normal.
Las fuentes deben contrastarse fuera de la respuesta del modelo.

Medir hechos observables: URLs indexadas y excluidas, canonical seleccionado,
errores de rastreo/schema, tráfico orgánico, citas observadas, correcciones y tiempo
desde un cambio normativo a la revisión del artículo. No usar el antiguo «100% GEO»
del panel como prueba de autoridad ni inferir cumplimiento de un producto por una
puntuación del CMS. Las métricas locales y tests no sustituyen Search Console/Bing
sobre el dominio público.

## Puertas de lanzamiento

- Instancia, usuarios y conexiones propias; sin demos, claves compartidas o QA.
- Identidad pública real, dominio/HTTPS y canal de correcciones comprobado.
- Contenido inicial revisado, fuentes accesibles, opiniones identificadas y fechas correctas.
- Permisos mínimos, revisiones y rechazo de acceso a contenido privado probados.
- URLs, idiomas, canonical, schema, sitemap, RSS y corpus coherentes; staging bloqueado.
- Backups/restauración, worker, scheduler, salud y recuperación de código probados.
- Correo/formularios comprobados con transporte real cuando formen parte del lanzamiento;
  un log no acredita recepción de correo.
- Registro de versión de dbCMS y plan de actualización de RegulaSE.

El objetivo de ser una referencia europea se demostrará con trabajo editorial,
transparencia y resultados medidos después del lanzamiento. No se puede certificar
esa reputación al copiar una plantilla. El procedimiento operativo está en
[GITHUB_TEMPLATE_REGULASE_RUNBOOK](GITHUB_TEMPLATE_REGULASE_RUNBOOK.md).
