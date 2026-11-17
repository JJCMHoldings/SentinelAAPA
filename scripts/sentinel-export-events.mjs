import { mkdir, rename, unlink, writeFile } from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";
import { randomUUID } from "node:crypto";

export function publicFeed(payload, baseUrl) {
    if (!Array.isArray(payload?.events)) throw new Error("Sentinel did not return an approved event feed.");
    const events = payload.events.map((event) => {
        for (const field of ["title", "description", "location", "start", "end", "imageUrl"]) {
            if (typeof event[field] !== "string") throw new Error(`Invalid public event field: ${field}`);
        }
        if (!Number.isInteger(event.id) || event.id < 1 || !Number.isFinite(Date.parse(event.start))
            || !Number.isFinite(Date.parse(event.end))) throw new Error("Invalid public event identity or dates.");
        const imageUrl = new URL(event.imageUrl, baseUrl);
        if (!["https:", "http:"].includes(imageUrl.protocol)) throw new Error("Invalid event image URL.");
        return { id: event.id, title: event.title, description: event.description, location: event.location,
            start: event.start, end: event.end, imageUrl: imageUrl.href };
    });
    return { events };
}

export async function exportEvents({ baseUrl, apiKey, output, request = fetch }) {
    const base = new URL(baseUrl.endsWith("/") ? baseUrl : `${baseUrl}/`);
    if (base.protocol !== "https:" && !(base.protocol === "http:" && ["localhost", "127.0.0.1", "[::1]"].includes(base.hostname))) {
        throw new Error("Use HTTPS for the Sentinel URL.");
    }
    if (!apiKey || !output || base.username || base.password || base.search || base.hash) throw new Error("Set a Sentinel URL, API key and output file.");
    const response = await request(new URL("plugins/sentinel/api/public-events", base), {
        headers: { "x-api-key": apiKey, Accept: "application/json" }, redirect: "error", signal: AbortSignal.timeout(15000),
    });
    if (!response.ok || !response.headers.get("content-type")?.includes("application/json")) throw new Error("Sentinel export failed; the previous feed was retained.");
    const feed = publicFeed(await response.json(), base);
    const target = path.resolve(output);
    await mkdir(path.dirname(target), { recursive: true });
    const temporary = `${target}.${randomUUID()}.tmp`;
    try {
        await writeFile(temporary, `${JSON.stringify(feed, null, 2)}\n`, { flag: "wx" });
        await rename(temporary, target);
    } finally {
        await unlink(temporary).catch((error) => { if (error.code !== "ENOENT") throw error; });
    }
    return feed.events.length;
}

if (process.argv[1] && pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url) {
    try {
        const count = await exportEvents({ baseUrl: process.env.SENTINEL_URL || "", apiKey: process.env.SENTINEL_API_KEY,
            output: process.argv[2] });
        console.log(`Exported ${count} approved Sentinel events.`);
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
