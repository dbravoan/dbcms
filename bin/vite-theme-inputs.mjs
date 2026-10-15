import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const identifier = /^[a-z0-9][a-z0-9-]*$/;

function directories(root, path) {
    return existsSync(join(root, path))
        ? readdirSync(join(root, path), { withFileTypes: true }).filter((entry) => entry.isDirectory()).map((entry) => entry.name).sort()
        : [];
}

/** One CSS entry per installed theme; a catalogue build keeps every switchable entry. */
export function themeBuildInputs(root) {
    const manifestPath = join(root, 'cms-project.json');
    const project = existsSync(manifestPath) ? JSON.parse(readFileSync(manifestPath, 'utf8')) : null;
    if (project && (project.schema !== 1 || !Array.isArray(project.themes) || project.themes.length === 0 || !project.themes.includes(project.default_theme))) {
        throw new Error('Invalid cms-project.json theme selection.');
    }
    const themes = project ? project.themes : directories(root, 'themes');
    const brands = project ? [project.brand] : directories(root, 'resources/views/themes/admin');
    return [
        ...themes.map((id) => ['theme', id, `themes/${id}/theme.css`]),
        ...brands.map((id) => ['brand', id, `resources/views/themes/admin/${id}/theme.css`]),
    ].flatMap(([kind, id, path]) => {
        if (typeof id !== 'string' || !identifier.test(id)) throw new Error(`Invalid ${kind} identifier.`);
        if (existsSync(join(root, path))) return [path];
        if (project) throw new Error(`Missing selected ${kind} stylesheet: ${path}`);
        return [];
    });
}
