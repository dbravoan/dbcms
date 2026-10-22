#!/usr/bin/env bash
set -euo pipefail
release_dir="${1:?Release directory is required}"
profile="${2:?Deployment profile is required}"
php_binary="${VPS_PHP_BINARY:-/usr/bin/php}"
[[ "$php_binary" =~ ^/[a-zA-Z0-9_./-]+$ ]] && test -x "$php_binary" || { echo 'VPS_PHP_BINARY must be an executable absolute PHP CLI path.' >&2; exit 1; }
"$php_binary" -r '
$root = $argv[1];
$expected = $argv[2];
try {
    $profile = json_decode(file_get_contents($root."/cms-project.json"), true, 512, JSON_THROW_ON_ERROR);
    if (($profile["deployment"] ?? null) !== $expected || !in_array($expected, ["github-actions", "rsync", "ansistrano"], true)) {
        throw new RuntimeException("The project deployment profile does not match this deployer.");
    }
    $policy = json_decode(file_get_contents($root."/resources/updates/release-policy.json"), true, 512, JSON_THROW_ON_ERROR);
    if (PHP_SAPI !== "cli" || version_compare(PHP_VERSION, $policy["min_php"] ?? "8.3.0", "<")) {
        throw new RuntimeException("The VPS PHP CLI version does not satisfy the release policy.");
    }
    $extensions = $policy["extensions"] ?? [];
    $lock = json_decode(file_get_contents($root."/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
    foreach (array_keys($lock["platform"] ?? []) as $requirement) {
        if (str_starts_with($requirement, "ext-")) $extensions[] = substr($requirement, 4);
    }
    foreach ($lock["packages"] ?? [] as $package) {
        foreach (array_keys($package["require"] ?? []) as $requirement) {
            if (str_starts_with($requirement, "ext-")) $extensions[] = substr($requirement, 4);
        }
    }
    foreach (array_unique($extensions) as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException("Missing PHP extension: ".$extension);
    }
    require $root."/vendor/composer/platform_check.php";
} catch (Throwable $error) {
    fwrite(STDERR, "Deployment preflight: ".$error->getMessage().PHP_EOL);
    exit(1);
}
' "$release_dir" "$profile"
