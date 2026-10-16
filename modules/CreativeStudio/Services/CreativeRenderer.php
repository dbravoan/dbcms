<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Services;

use Illuminate\Validation\ValidationException;
use Modules\Marketing\Contracts\MarketingArtifactStoreInterface;
use Modules\Marketing\DTOs\ArtifactDescriptor;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class CreativeRenderer
{
    public function __construct(private readonly MarketingArtifactStoreInterface $store) {}

    /** @param array<string,mixed> $brand */
    public function assertReady(string $format, array $brand): void
    {
        if ($format === 'text') {
            return;
        }
        $font = (string) config('creative_studio.fonts.'.(string) ($brand['font_id'] ?? 'default'), '');
        if (! function_exists('imagecreatetruecolor') || ! is_file($font) || empty($brand['font_hash']) || ! hash_equals((string) $brand['font_hash'], (string) hash_file('sha256', $font))) {
            throw ValidationException::withMessages(['template' => 'Comprueba GD y la fuente empaquetada; después guarda la marca de nuevo. Consulta la guía de composición.']);
        }
        if ($format === 'video' && ((new ExecutableFinder)->find((string) config('creative_studio.ffmpeg')) === null || (new ExecutableFinder)->find((string) config('creative_studio.ffprobe')) === null)) {
            throw ValidationException::withMessages(['template' => 'El vídeo requiere FFmpeg y FFprobe. Consulta la guía de composición.']);
        }
    }

    /**
     * @param  list<ArtifactDescriptor>  $assets
     * @param  array<string,mixed>  $brand
     * @param  array<string,mixed>  $template
     * @return list<ArtifactDescriptor>
     */
    public function render(string $format, array $assets, string $text, array $brand, array $template, string $owner, int $duration = 30, string $imageEncoding = 'png'): array
    {
        if ($format === 'text') {
            return [];
        }
        if ($assets === []) {
            throw new RuntimeException('La plantilla necesita material multimedia.');
        }
        if ($format === 'video') {
            return [$this->video($assets, $text, $brand, $template, $owner, $duration)];
        }

        return array_map(fn (ArtifactDescriptor $asset): ArtifactDescriptor => $this->image($asset, $text, $brand, $template, $owner, $imageEncoding), $assets);
    }

    /**
     * @param  array<string,mixed>  $brand
     * @param  array<string,mixed>  $template
     */
    private function image(ArtifactDescriptor $asset, string $text, array $brand, array $template, string $owner, string $imageEncoding): ArtifactDescriptor
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD no está instalado. Consulta la guía de composición.');
        }
        $image = $this->canvas($asset, $text, $brand, $template, false);
        $temporary = tempnam(sys_get_temp_dir(), 'creative-image-');
        if ($temporary === false) {
            imagedestroy($image);
            throw new RuntimeException('No hay espacio temporal.');
        }
        try {
            if (! ($imageEncoding === 'jpg' ? imagejpeg($image, $temporary, 90) : imagepng($image, $temporary))) {
                throw new RuntimeException('No se pudo guardar la composición.');
            }

            return $this->save($temporary, $owner, 'creative.'.$imageEncoding, $imageEncoding === 'jpg' ? 'image/jpeg' : 'image/png');
        } finally {
            imagedestroy($image);
            @unlink($temporary);
        }
    }

    /**
     * @param  list<ArtifactDescriptor>  $assets
     * @param  array<string,mixed>  $brand
     * @param  array<string,mixed>  $template
     */
    private function video(array $assets, string $text, array $brand, array $template, string $owner, int $duration): ArtifactDescriptor
    {
        $ffmpeg = (new ExecutableFinder)->find((string) config('creative_studio.ffmpeg')) ?? '';
        $ffprobe = (new ExecutableFinder)->find((string) config('creative_studio.ffprobe')) ?? '';
        if (! is_executable($ffmpeg) || ! is_executable($ffprobe)) {
            throw new RuntimeException('FFmpeg y FFprobe deben estar instalados. Consulta la guía de composición.');
        }
        $directory = sys_get_temp_dir().'/creative-render-'.bin2hex(random_bytes(12));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('No se puede preparar el render.');
        }
        $overlay = null;
        try {
            $overlay = $this->canvas(null, '', $brand, ['width' => 1080, 'height' => 1920, 'safe_margin' => 100], true);
            imagepng($overlay, $directory.'/brand.png');
            file_put_contents($directory.'/captions.srt', $this->captions($text, $duration));
            $args = [$ffmpeg, '-hide_banner', '-loglevel', 'error', '-y'];
            $filters = [];
            foreach ($assets as $index => $asset) {
                if (! $this->store->verify($asset)) {
                    throw new RuntimeException('El material ha cambiado antes del render.');
                }
                $path = $this->store->absolutePath($asset);
                $sceneDuration = $duration / count($assets);
                if (str_starts_with($asset->mimeType, 'image/')) {
                    $args = [...$args, '-loop', '1', '-framerate', '25', '-i', $path];
                } elseif (str_starts_with($asset->mimeType, 'video/')) {
                    $this->probe($path);
                    $args = [...$args, '-stream_loop', '-1', '-i', $path];
                } else {
                    throw new RuntimeException('La composición requiere imágenes o clips.');
                }
                $filters[] = '['.$index.':v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,setsar=1,fps=25,trim=duration='.number_format($sceneDuration, 4, '.', '').',setpts=PTS-STARTPTS[v'.$index.']';
            }
            $args = [...$args, '-loop', '1', '-i', $directory.'/brand.png'];
            $inputs = implode('', array_map(static fn (int $i): string => '[v'.$i.']', array_keys($assets)));
            $filters[] = $inputs.'concat=n='.count($assets).':v=1:a=0[sequence]';
            $filters[] = '[sequence]['.count($assets).':v]overlay=0:0[branded]';
            $filters[] = '[branded]subtitles='.$directory."/captions.srt:force_style='FontName=DejaVu Sans,FontSize=22,PrimaryColour=&HFFFFFF&,OutlineColour=&H00101828&,BorderStyle=1,Outline=2,Alignment=2,MarginV=120'[output]";
            $args = [...$args, '-filter_complex', implode(';', $filters), '-map', '[output]', '-an', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '22', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-t', (string) $duration, '-threads', '2', $directory.'/creative.mp4'];
            $process = new Process($args);
            $process->setTimeout(240);
            $process->mustRun();
            $metadata = $this->probe($directory.'/creative.mp4');
            if ($metadata['width'] !== 1080 || $metadata['height'] !== 1920 || abs($metadata['duration'] - $duration) > 0.5) {
                throw new RuntimeException('El vídeo final no cumple la plantilla.');
            }
            $artifact = $this->save($directory.'/creative.mp4', $owner, 'creative.mp4', 'video/mp4');

            return new ArtifactDescriptor($artifact->id, $artifact->path, $artifact->sha256, $artifact->mimeType, $artifact->sizeBytes, $metadata['width'], $metadata['height'], $metadata['duration'], ['codec' => $metadata['codec'], 'video_codec' => $metadata['codec'], 'fps' => 25, 'audio' => false, 'captions' => 'burned_in', 'template_version' => $template['version'] ?? 1]);
        } finally {
            if ($overlay instanceof \GdImage) {
                imagedestroy($overlay);
            }foreach (['brand.png', 'captions.srt', 'creative.mp4'] as $file) {
                @unlink($directory.'/'.$file);
            }@rmdir($directory);
        }
    }

    /**
     * @param  array<string,mixed>  $brand
     * @param  array<string,mixed>  $template
     */
    private function canvas(?ArtifactDescriptor $asset, string $text, array $brand, array $template, bool $transparent): \GdImage
    {
        $width = max(1, (int) $template['width']);
        $height = max(1, (int) $template['height']);
        $margin = (int) $template['safe_margin'];
        $fontId = (string) ($brand['font_id'] ?? 'default');
        $font = (string) config('creative_studio.fonts.'.$fontId, '');
        if (! is_file($font) || ! isset($brand['font_hash']) || ! hash_equals((string) $brand['font_hash'], (string) hash_file('sha256', $font))) {
            throw new RuntimeException('Falta la fuente de composición. Consulta la guía de medios.');
        }
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('No se pudo crear la composición.');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $background = $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : $this->color($image, (string) $brand['background']);
        if ($background === false) {
            throw new RuntimeException('No se pudo preparar el fondo.');
        }
        imagefill($image, 0, 0, $background);
        imagealphablending($image, true);
        if ($asset !== null) {
            if (! $this->store->verify($asset)) {
                throw new RuntimeException('La imagen ha cambiado antes de componer.');
            }
            $path = $this->store->absolutePath($asset);
            $dimensions = getimagesize($path);
            if ($dimensions === false || $dimensions[0] * $dimensions[1] > 40000000) {
                throw new RuntimeException('La imagen no puede procesarse.');
            }
            $source = match ($asset->mimeType) {
                'image/png' => imagecreatefrompng($path),'image/jpeg' => imagecreatefromjpeg($path),'image/webp' => imagecreatefromwebp($path),default => false
            };
            if (! $source instanceof \GdImage) {
                throw new RuntimeException('Imagen incompatible con la plantilla.');
            }
            $photoHeight = (int) ($height * 0.68);
            $ratio = max($width / $dimensions[0], $photoHeight / $dimensions[1]);
            $cropWidth = (int) ($width / $ratio);
            $cropHeight = (int) ($photoHeight / $ratio);
            imagecopyresampled($image, $source, 0, 0, (int) (($dimensions[0] - $cropWidth) / 2), (int) (($dimensions[1] - $cropHeight) / 2), $width, $photoHeight, $cropWidth, $cropHeight);
            imagedestroy($source);
        }
        $foreground = $this->color($image, (string) $brand['foreground']);
        $accent = $this->color($image, (string) $brand['accent']);
        imagefilledrectangle($image, $margin, (int) ($height * 0.72), $margin + 80, (int) ($height * 0.72) + 7, $accent);
        $lineY = (int) ($height * 0.79);
        $lines = $this->wrap(mb_substr(strip_tags($text), 0, 220), $font, 36, $width - 2 * $margin);
        foreach (array_slice($lines, 0, 4) as $line) {
            imagettftext($image, 36, 0, $margin, $lineY, $foreground, $font, $line);
            $lineY += 55;
        }
        imagettftext($image, 22, 0, $margin, $height - $margin, $foreground, $font, mb_substr((string) $brand['name'], 0, 65));
        if (isset($brand['logo'])) {
            $logo = ArtifactDescriptor::fromArray((array) $brand['logo']);
            if (! $this->store->verify($logo)) {
                throw new RuntimeException('El logo ha cambiado antes del render.');
            }
            $logoPath = $this->store->absolutePath($logo);
            $logoImage = match ($logo->mimeType) {
                'image/png' => imagecreatefrompng($logoPath),'image/jpeg' => imagecreatefromjpeg($logoPath),'image/webp' => imagecreatefromwebp($logoPath),default => false
            };
            if (! $logoImage instanceof \GdImage) {
                throw new RuntimeException('El logo no puede componerse.');
            }$logoWidth = imagesx($logoImage);
            $logoHeight = imagesy($logoImage);
            $ratio = min(220 / $logoWidth, 110 / $logoHeight);
            imagecopyresampled($image, $logoImage, $width - $margin - (int) ($logoWidth * $ratio), $margin, 0, 0, (int) ($logoWidth * $ratio), (int) ($logoHeight * $ratio), $logoWidth, $logoHeight);
            imagedestroy($logoImage);
        }

        return $image;
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, int $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            $candidate = trim($line.' '.$word);
            $box = imagettfbbox($size, 0, $font, $candidate);
            if ($box !== false && $width < $box[2] - $box[0] && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function color(\GdImage $image, string $hex): int
    {
        $rgb = sscanf($hex, '#%02x%02x%02x');

        $color = imagecolorallocate($image, max(0, min(255, (int) ($rgb[0] ?? 0))), max(0, min(255, (int) ($rgb[1] ?? 0))), max(0, min(255, (int) ($rgb[2] ?? 0))));
        if ($color === false) {
            throw new RuntimeException('No se pudo asignar el color.');
        }

        return $color;
    }

    private function save(string $path, string $owner, string $filename, string $mime): ArtifactDescriptor
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('No se puede leer la composición.');
        }try {
            return $this->store->writeStream($stream, $owner, $filename, $mime);
        } finally {
            fclose($stream);
        }
    }

    /** @return array{width:int,height:int,duration:float,codec:string} */
    private function probe(string $path): array
    {
        $process = new Process([(string) config('creative_studio.ffprobe'), '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=codec_name,width,height:format=duration', '-of', 'json', $path]);
        $process->setTimeout(20);
        $process->mustRun();
        $data = json_decode($process->getOutput(), true);
        $stream = is_array($data) ? (array) ($data['streams'][0] ?? []) : [];
        $duration = (float) ($data['format']['duration'] ?? 0);
        if ((int) ($stream['width'] ?? 0) < 1 || $duration <= 0) {
            throw new RuntimeException('FFprobe no reconoce un clip válido.');
        }

        return ['width' => (int) $stream['width'], 'height' => (int) $stream['height'], 'duration' => $duration, 'codec' => (string) ($stream['codec_name'] ?? '')];
    }

    private function captions(string $text, int $duration): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', strip_tags($text)) ?: [$text];
        $sentences = array_values(array_filter($sentences));
        $sentences = array_slice($sentences, 0, 20);
        $count = max(1, count($sentences));
        $output = '';
        foreach ($sentences as $index => $sentence) {
            $start = (int) floor($duration * $index / $count);
            $end = (int) floor($duration * ($index + 1) / $count);
            $output .= ($index + 1)."\n".sprintf('00:%02d:%02d,000', $start / 60, $start % 60).' --> '.sprintf('00:%02d:%02d,000', $end / 60, $end % 60)."\n".str_replace(["\r", "\n", '-->'], [' ', ' ', '—'], mb_substr($sentence, 0, 240))."\n\n";
        }

        return $output;
    }
}
