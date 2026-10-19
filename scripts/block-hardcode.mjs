import { readdir, readFile } from "node:fs/promises";
import { dirname, extname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { execFileSync } from 'node:child_process';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");

const TEXT_EXTENSIONS = new Set([
    ".js",
    ".mjs",
    ".php",
    ".json",
    ".md",
    ".html",
    ".css",
    ".ini",
    ".htaccess",
    ".txt",
    ".env",
    ".example"
]);

const IGNORED_DIRECTORIES = new Set([
    ".git",
    "node_modules",
    "img",
    "storage"
]);

const IGNORED_FILES = new Set([
    "api/.env"
]);

const ALLOWED_EMAIL_DOMAINS = new Set([
    "example.com",
    "empresa.com",
    "tu-dominio.com"
]);

function isTextFile(filePath) {
    const extension = extname(filePath).toLowerCase();

    if (TEXT_EXTENSIONS.has(extension)) {
        return true;
    }

    return filePath.endsWith(".env.example");
}

async function walk(directoryPath) {
    const entries = await readdir(directoryPath, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        const absolutePath = join(directoryPath, entry.name);
        const repoPath = relative(ROOT, absolutePath).replace(/\\/g, "/");

        if (entry.isDirectory()) {
            if (!IGNORED_DIRECTORIES.has(entry.name)) {
                files.push(...await walk(absolutePath));
            }

            continue;
        }

        if (IGNORED_FILES.has(repoPath)) {
            continue;
        }

        if (isTextFile(repoPath)) {
            files.push({ absolutePath, repoPath });
        }
    }

    return files;
}

function extractViolations(content, repoPath) {
    const violations = [];
    const lines = content.split(/\r?\n/);

    const secretAssignmentPattern = /^\s*[A-Z][A-Z0-9_]*(?:KEY|TOKEN|SECRET|PASSWORD)\s*=\s*([^\s#]+)/;
    const emailPattern = /\b[A-Z0-9._%+-]+@([A-Z0-9.-]+\.[A-Z]{2,})\b/gi;
    const phoneHintPattern = /\b(?:whatsapp|telefono|phone|tel)\b[^\n]{0,40}\b\d{9,15}\b/i;

    lines.forEach((line, index) => {
        const lineNumber = index + 1;
        if (/\b(?:gsk_|sk-)[A-Za-z0-9_-]{20,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(line)) {
            violations.push(`${repoPath}:${lineNumber} posible secreto (valor oculto)`);
        }
        const secretMatch = line.match(secretAssignmentPattern);

        if (secretMatch) {
            const value = String(secretMatch[1] || "").trim();
            const normalized = value.toLowerCase();
            const looksPlaceholder = normalized === ""
                || normalized === '""'
                || normalized === "''"
                || normalized === "null"
                || normalized === "undefined"
                || normalized === "changeme"
                || normalized === "tu_valor"
                || value.startsWith("<")
                || value.includes("tu_")
                || value.includes("your_");

            if (!looksPlaceholder) {
                violations.push(`${repoPath}:${lineNumber} secret hardcode detectado (valor oculto)`);
            }
        }

        if (phoneHintPattern.test(line)) {
            violations.push(`${repoPath}:${lineNumber} telefono hardcode detectado`);
        }

        let emailMatch = null;

        while ((emailMatch = emailPattern.exec(line)) !== null) {
            const domain = String(emailMatch[1] || "").toLowerCase();

            if (ALLOWED_EMAIL_DOMAINS.has(domain)) {
                continue;
            }

            violations.push(`${repoPath}:${lineNumber} email hardcode detectado (valor oculto)`);
        }
    });

    return violations;
}

async function main() {
    const files = await walk(ROOT);
    const violations = [];

    for (const file of files) {
        const raw = await readFile(file.absolutePath, "utf8");
        violations.push(...extractViolations(raw, file.repoPath));
    }

    const tracked = execFileSync('git', ['ls-files', '-z'], { cwd: ROOT, encoding: 'utf8' }).split('\0').filter(Boolean);
    for (const path of tracked) {
        if (/(^|\/)\.env$|^storage\/.*\.(?:ndjson|json|tmp|lock)$/.test(path)) violations.push(`${path}: archivo privado versionado`);
    }
    if (process.argv.includes('--history')) {
        const commits = execFileSync('git', ['rev-list', '--all'], { cwd: ROOT, encoding: 'utf8' }).trim().split(/\s+/).filter(Boolean);
        for (const commit of commits) {
            const paths = execFileSync('git', ['ls-tree', '-r', '--name-only', '-z', commit], { cwd: ROOT, encoding: 'utf8' }).split('\0').filter(isTextFile);
            for (const path of paths) {
                if (path === 'api/.env') { violations.push(`${commit.slice(0, 8)}: archivo de secretos en historial`); continue; }
                const raw = execFileSync('git', ['show', `${commit}:${path}`], { cwd: ROOT, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
                if (/\b(?:gsk_|sk-)[A-Za-z0-9_-]{20,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(raw)) {
                    violations.push(`${commit.slice(0, 8)}:${path}: posible secreto historico (valor oculto)`);
                }
            }
        }
    }

    if (violations.length) {
        console.error("Hardcode guard: se detectaron posibles hardcodes bloqueados:");
        violations.forEach((item) => console.error(`- ${item}`));
        process.exit(1);
    }

    console.log("Hardcode guard OK");
}

main().catch((error) => {
    console.error("Error ejecutando hardcode guard:", error?.message || error);
    process.exit(1);
});
