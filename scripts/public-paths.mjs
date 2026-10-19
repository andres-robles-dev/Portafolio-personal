import { realpath } from 'node:fs/promises';
import { extname, relative, resolve, sep } from 'node:path';

function isPublic(target) {
    return ['index.html', 'asistente.html', 'release.json'].includes(target)
        || (/^(assets|img)\//.test(target) && /\.(css|js|svg|png|jpe?g|gif|webp|ico|woff2?|ttf|otf|avif|mp4)$/i.test(target));
}

export async function resolvePublicPath(root, requestUrl) {
    const path = decodeURIComponent(String(requestUrl).split('?')[0]);
    if (path.includes('\\') || path.split('/').some((part) => part.startsWith('.'))) return null;
    const target = path === '/' ? 'index.html' : path.replace(/^\//, '');
    if (!isPublic(target)) return null;
    const base = await realpath(root);
    const file = await realpath(resolve(base, target));
    const canonical = process.platform === 'win32' ? file.toLowerCase() : file;
    const prefix = process.platform === 'win32' ? (base + sep).toLowerCase() : base + sep;
    if (!canonical.startsWith(prefix) || !isPublic(relative(base, file).replace(/\\/g, '/'))) return null;
    return { file, extension: extname(file).toLowerCase() };
}
