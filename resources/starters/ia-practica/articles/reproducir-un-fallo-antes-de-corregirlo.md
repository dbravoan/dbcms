Convierte un informe impreciso en una reproducción, una hipótesis y una verificación.

## Objetivo

Transformar una incidencia en pasos reproducibles y una propuesta de cambio revisable.

## Entradas

Mensaje de error, versión del entorno, pasos observados y fragmento mínimo sin secretos.

## Paso a paso

1. Elimina claves, tokens y datos reales de los registros.
2. Pide separar comportamiento esperado, observado e información ausente.
3. Construye una reproducción mínima en un entorno aislado.
4. Pide hipótesis y una propuesta de cambio pequeña, indicando sus supuestos.
5. Verifica la reproducción y las pruebas pertinentes; revisa el diff antes de integrar.

## Prompt de ejemplo

> Analiza este caso anonimizado. Primero pregunta por lo imprescindible para reproducirlo. Después enumera hipótesis y una comprobación que pueda refutar cada una. No propongas ejecutar comandos destructivos ni afirmes haber probado nada.

## Verificación humana

Reproduce el fallo original, comprueba que el cambio lo resuelve y revisa efectos sobre permisos, datos y consumidores.

## Entregable

Incidencia reproducible, cambio pequeño y evidencia de las comprobaciones ejecutadas.

## Límites

Una respuesta de IA no demuestra que el código compile ni sea seguro. La integración y el despliegue requieren la revisión del equipo.

## Transferencia entre departamentos

Entrega el resultado junto con sus fuentes y dudas. El equipo receptor revisa los hechos que usará, identifica a la persona que aprueba y devuelve las correcciones al propietario del documento. Evita reenviar conversaciones completas con datos que no necesita.

[Volver a las cheatsheets]({{url:cheatsheets}}) · [Consultar la metodología]({{url:metodologia}})
