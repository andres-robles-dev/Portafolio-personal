import { createServer } from "node:http";
import { readFile } from "node:fs/promises";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { resolvePublicPath } from "./scripts/public-paths.mjs";

const HOST = "127.0.0.1";
const PORT = Number(process.env.PORT || 4173);
const ROOT = dirname(fileURLToPath(import.meta.url));

const MIME = {
    ".html": "text/html; charset=utf-8",
    ".css": "text/css; charset=utf-8",
    ".js": "text/javascript; charset=utf-8",
    ".json": "application/json; charset=utf-8",
    ".jpg": "image/jpeg",
    ".jpeg": "image/jpeg",
    ".png": "image/png",
    ".svg": "image/svg+xml",
    ".ico": "image/x-icon",
    ".webp": "image/webp",
    ".avif": "image/avif",
    ".woff": "font/woff",
    ".woff2": "font/woff2",
    ".ttf": "font/ttf",
    ".otf": "font/otf",
    ".mp4": "video/mp4"
};

function cacheHeadersForExtension(extension) {
    if (extension === ".html") {
        return {
            "Cache-Control": "no-store, no-cache, must-revalidate, max-age=0",
            "Pragma": "no-cache",
            "Expires": "0"
        };
    }

    if ([".css", ".js", ".mjs", ".json"].includes(extension)) {
        return {
            "Cache-Control": "no-cache, must-revalidate, max-age=0"
        };
    }

    if ([".jpg", ".jpeg", ".png", ".svg", ".ico"].includes(extension)) {
        return {
            "Cache-Control": "public, max-age=2592000"
        };
    }

    return {
        "Cache-Control": "no-cache, max-age=0"
    };
}

const server = createServer(async (req, res) => {
    try {
        const resolved = await resolvePublicPath(ROOT, req.url || "/");
        if (!resolved) throw new Error("Not public");
        const file = await readFile(resolved.file);
        const extension = resolved.extension;
        const contentType = MIME[extension] || "application/octet-stream";

        res.writeHead(200, {
            "Content-Type": contentType,
            "X-Content-Type-Options": "nosniff",
            ...cacheHeadersForExtension(extension)
        });
        res.end(file);
    } catch {
        res.writeHead(404, { "Content-Type": "text/plain; charset=utf-8" });
        res.end("Not found");
    }
});

server.listen(PORT, HOST, () => {
    console.log(`Servidor local en http://${HOST}:${PORT}`);
});
