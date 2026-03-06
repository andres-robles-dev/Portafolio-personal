import process from "node:process";
import { readFile, writeFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(SCRIPT_DIR, "..");
const RELEASE_PATH = resolve(ROOT, "release.json");

const TARGETS = [
    {
        filePath: resolve(ROOT, "index.html"),
        assets: [
            "assets/css/main.css",
            "assets/js/main.js"
        ]
    },
    {
        filePath: resolve(ROOT, "asistente.html"),
        assets: [
            "assets/css/assistant.css",
            "assets/js/static-nav.js",
            "assets/js/assistant.js"
        ]
    }
];

function pad(value) {
    return String(value).padStart(2, "0");
}

function createReleaseId() {
    const now = new Date();

    return [
        now.getUTCFullYear(),
        pad(now.getUTCMonth() + 1),
        pad(now.getUTCDate()),
        "-",
        pad(now.getUTCHours()),
        pad(now.getUTCMinutes()),
        pad(now.getUTCSeconds())
    ].join("");
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function applyVersionToHtml(html, assets, releaseId) {
    let updated = html;
    const touched = [];

    for (const asset of assets) {
        const pattern = new RegExp(`${escapeRegExp(asset)}(?:\\?v=[^"']*)?`, "g");
        let count = 0;

        updated = updated.replace(pattern, () => {
            count += 1;
            return `${asset}?v=${releaseId}`;
        });

        if (count === 0) {
            throw new Error(`No se encontro referencia a '${asset}' en el HTML objetivo.`);
        }

        touched.push({ asset, count });
    }

    return {
        html: updated,
        touched
    };
}

async function writeReleaseFile(releaseId) {
    const payload = {
        releaseId,
        updatedAt: new Date().toISOString()
    };

    await writeFile(RELEASE_PATH, `${JSON.stringify(payload, null, 4)}\n`, "utf8");
}

async function readReleaseId() {
    const raw = await readFile(RELEASE_PATH, "utf8");
    const parsed = JSON.parse(raw);
    const releaseId = String(parsed.releaseId || "").trim();

    if (!releaseId) {
        throw new Error("release.json existe pero no contiene releaseId valido.");
    }

    return releaseId;
}

async function syncHtml(releaseId) {
    const changes = [];

    for (const target of TARGETS) {
        const original = await readFile(target.filePath, "utf8");
        const result = applyVersionToHtml(original, target.assets, releaseId);

        if (result.html !== original) {
            await writeFile(target.filePath, result.html, "utf8");
        }

        changes.push({
            filePath: target.filePath,
            touched: result.touched
        });
    }

    return changes;
}

async function verify(releaseId) {
    for (const target of TARGETS) {
        const html = await readFile(target.filePath, "utf8");

        for (const asset of target.assets) {
            const expected = `${asset}?v=${releaseId}`;

            if (!html.includes(expected)) {
                throw new Error(`Version inconsistente en ${target.filePath}: falta '${expected}'.`);
            }
        }
    }
}

function printChanges(releaseId, changes) {
    console.log(`Release aplicada: ${releaseId}`);

    for (const item of changes) {
        const shortPath = item.filePath.replace(`${ROOT}\\`, "");
        console.log(`- ${shortPath}`);

        for (const touched of item.touched) {
            console.log(`  - ${touched.asset} (${touched.count})`);
        }
    }
}

async function main() {
    const [, , commandArg] = process.argv;
    const command = (commandArg || "bump").trim().toLowerCase();

    if (command === "bump") {
        const releaseId = createReleaseId();
        const changes = await syncHtml(releaseId);
        await writeReleaseFile(releaseId);
        await verify(releaseId);
        printChanges(releaseId, changes);
        return;
    }

    if (command === "sync") {
        const releaseId = await readReleaseId();
        const changes = await syncHtml(releaseId);
        await verify(releaseId);
        printChanges(releaseId, changes);
        return;
    }

    if (command === "verify") {
        const releaseId = await readReleaseId();
        await verify(releaseId);
        console.log(`Version verificada correctamente: ${releaseId}`);
        return;
    }

    throw new Error("Uso: node scripts/release-version.mjs [bump|sync|verify]");
}

main().catch((error) => {
    console.error("Error de versionado:", error?.message || error);
    process.exit(1);
});
