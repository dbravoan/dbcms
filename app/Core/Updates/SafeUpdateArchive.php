<?php

declare(strict_types=1);

namespace App\Core\Updates;

final class SafeUpdateArchive
{
    /** @param array<string,string> $inventory */
    public function extract(string $archive, string $destination, array $inventory): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new \DomainException('El paquete ZIP no se puede abrir.');
        }
        try {
            if ($zip->numFiles > (int) config('updates.max_files', 50000)) {
                throw new \DomainException('El paquete contiene demasiados archivos.');
            }
            $seen = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry === false) {
                    throw new \DomainException('Entrada ZIP ilegible.');
                }
                $path = $entry['name'];
                (new UpdatePathPolicy)->requireAllowed($path);
                $opsys = $attributes = 0;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if (isset($seen[strtolower($path)]) || ! isset($inventory[$path]) || str_ends_with($path, '/') || ($opsys === 3 && ! in_array($type, [0, 0100000], true))) {
                    throw new \DomainException('Archivo ZIP duplicado, enlazado o ajeno al inventario.');
                }
                $seen[strtolower($path)] = true;
                $total += $entry['size'];
                if ($total > (int) config('updates.max_expanded_bytes', 2147483648) || $entry['size'] > max(10485760, $entry['comp_size'] * 200)) {
                    throw new \DomainException('El paquete supera los límites de extracción.');
                }
            }
            if (count($seen) !== count($inventory)) {
                throw new \DomainException('El paquete no contiene todo el inventario firmado.');
            }
            $space = disk_free_space(dirname($destination));
            if ($space === false || $space < $total * 2 + 10485760) {
                throw new \DomainException('No hay espacio suficiente para preparar la actualización.');
            }
            if (! mkdir($destination, 0700, true)) {
                throw new \DomainException('No se puede crear la preparación privada.');
            }
            foreach ($inventory as $path => $hash) {
                $target = $destination.'/'.$path;
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true)) {
                    throw new \RuntimeException('No se puede preparar un directorio.');
                }
                $input = $zip->getStream($path);
                $output = fopen($target, 'xb');
                if ($input === false || $output === false) {
                    throw new \RuntimeException('No se puede extraer un archivo.');
                }
                try {
                    $expected = $zip->statName($path)['size'] ?? -1;
                    $copied = stream_copy_to_stream($input, $output, min($expected + 1, (int) config('updates.max_expanded_bytes', 2147483648) + 1));
                    if ($copied === false || $copied !== $expected || ! feof($input)) {
                        throw new \RuntimeException('Extracción interrumpida.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                chmod($target, 0600);
                if (hash_file('sha256', $target) !== $hash) {
                    throw new \DomainException('El contenido extraído no coincide con el inventario.');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
