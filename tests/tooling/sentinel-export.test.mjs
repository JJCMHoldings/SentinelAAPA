import test from "node:test";
import assert from "node:assert/strict";
import { mkdtemp, readFile, rm, writeFile } from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { exportEvents, publicFeed } from "../../scripts/sentinel-export-events.mjs";

const event = { id: 1, title: "Training", description: "Public description", location: "Public hall",
    start: "2026-12-01T12:00:00Z", end: "2026-12-01T14:00:00Z", imageUrl: "/crm/plugins/core/sentinel/assets/aapa-event.svg" };

test("export publishes only approved fields and resolves artwork at a subdirectory install", () => {
    const output = publicFeed({ events: [{ ...event, privateNotes: "secret", draft: { title: "Not approved" } }] }, "https://example.org/crm/");
    assert.deepEqual(Object.keys(output.events[0]), ["id", "title", "description", "location", "start", "end", "imageUrl"]);
    assert.equal(output.events[0].imageUrl, "https://example.org/crm/plugins/core/sentinel/assets/aapa-event.svg");
    assert.throws(() => publicFeed({ events: [{ ...event, imageUrl: "javascript:alert(1)" }] }, "https://example.org/"));
});

test("failed exports retain the last feed and a successful withdrawal clears it without touching archives", async () => {
    const directory = await mkdtemp(path.join(os.tmpdir(), "sentinel-export-"));
    const output = path.join(directory, "sentinel-events.json");
    const archive = path.join(directory, "historical-event.html");
    try {
        await writeFile(output, "previous feed");
        await writeFile(archive, "historical page");
        const options = { baseUrl: "https://example.org/crm", apiKey: "test-only", output };
        await assert.rejects(exportEvents({ ...options, request: async () => new Response("unavailable", { status: 503 }) }));
        assert.equal(await readFile(output, "utf8"), "previous feed");
        await assert.rejects(exportEvents({ ...options, request: async () => Response.json({ draft: [] }) }));
        assert.equal(await readFile(output, "utf8"), "previous feed");
        const count = await exportEvents({ ...options, request: async (url, request) => {
            assert.equal(url.href, "https://example.org/crm/plugins/sentinel/api/public-events");
            assert.equal(request.headers["x-api-key"], "test-only");
            assert.equal(request.redirect, "error");
            return Response.json({ events: [] });
        } });
        assert.equal(count, 0);
        assert.deepEqual(JSON.parse(await readFile(output, "utf8")), { events: [] });
        assert.equal(await readFile(archive, "utf8"), "historical page");
    } finally {
        await rm(directory, { recursive: true, force: true });
    }
});
