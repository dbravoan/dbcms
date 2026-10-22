<?php

declare(strict_types=1);

use App\DTOs\Integrations\GuideStep;
use App\DTOs\Integrations\SetupGuideDescriptor;

return [new SetupGuideDescriptor('media-composition', 'Composición multimedia', 'Preparar plantillas, logos y vídeo', ['Contenedor con GD/Freetype, fuente TTF legible, FFmpeg/FFprobe y almacenamiento privado.', 'Permiso manage:media; workers creative-generation y creative-render activos.'], [
    new GuideStep('prepare', 'Inventariar material', 'Comprueba los derechos de uso de fotografías, logos y clips.', 'Elige formato cuadrado, vertical o vídeo corto y el objetivo de campaña.', 'Material autorizado y formato definido.'),
    new GuideStep('runtime', 'Verificar el contenedor', 'Incluye GD, Freetype, FFmpeg y FFprobe en el artefacto de release.', 'Comprueba las rutas configuradas y acceso del worker al directorio temporal.', 'El entorno puede generar imágenes y examinar vídeo.', ['Filtros FFmpeg' => 'https://ffmpeg.org/ffmpeg-filters.html']),
    new GuideStep('font', 'Preparar tipografía', 'Conserva la licencia y versión de una fuente TTF disponible para render.', 'Configura MARKETING_FONT_PATH y comprueba que el worker la puede leer.', 'Fuente estable identificada para las nuevas revisiones.'),
    new GuideStep('brand', 'Guardar la marca', 'Prepara logo PNG con transparencia y paleta de colores.', 'Sube el logo a la campaña, selecciónalo en Marca y guarda nombre, fondo, texto y acento.', 'Marca versionada; las revisiones anteriores conservan su snapshot.'),
    new GuideStep('materials', 'Importar materiales', 'Prepara PNG/JPEG/WebP o MP4/MOV válidos.', 'Sube archivos privados a la campaña y selecciona su orden para componer. El vídeo final es silencioso: la composición descarta el audio de los clips.', 'Archivos examinados y aislados en almacenamiento de la instalación.'),
    new GuideStep('compose', 'Componer una muestra', 'Reserva recursos de CPU y espacio temporal.', 'Escribe el mensaje, selecciona materiales y pulsa Componer. Revisa texto, logo y subtítulos.', 'Imagen final o vídeo vertical con dimensiones reales; ninguna publicación social ejecutada.'),
    new GuideStep('export', 'Exportar y mantener', 'Comprueba las especificaciones de la red elegida.', 'Descarga el ZIP de la revisión, verifica manifest y archivos; conserva backups y versiones de plantillas.', 'Material exportable y revisión recuperable.'),
], ['Falta la fuente: instala el TTF y corrige MARKETING_FONT_PATH.', 'FFprobe falla: revisa el archivo y el binario del contenedor.', 'El worker se interrumpe: comprueba la tarea antes de reanudar.'], ['Filtros FFmpeg' => 'https://ffmpeg.org/ffmpeg-filters.html', 'GD' => 'https://www.php.net/manual/en/book.image.php'], '2026-10-13', '1.0.0')];
