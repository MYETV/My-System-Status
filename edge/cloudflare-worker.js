/**
 * path: edge/cloudflare-worker.js
 * Cloudflare Worker: Edge Probe Runner & Autonomous Sentinel with Outage Recovery Logger
 * 
 * Environment Variables:
 * - SHARED_SECRET_TOKEN: Shared secret string
 * - ORIGIN_STATUS_URL: e.g. "https://mysystemstatus.myetv.tv"
 * - DISCORD_WEBHOOK_URL: Discord webhook URL
 * - STATUS_KV: KV Namespace Binding (Optional, has automatic Cache fallback)
 */

export default {
    // 1. Web Probes Dispatcher (Called by PHP cron when server is running)
    async fetch(request, env) {
        if (request.method !== "POST") {
            return new Response("Method Not Allowed", { status: 405 });
        }

        const authHeader = request.headers.get("Authorization") || "";
        const expectedToken = "Bearer " + (env.SHARED_SECRET_TOKEN || "");
        if (authHeader !== expectedToken) {
            return new Response(JSON.stringify({ error: "Unauthorized" }), {
                status: 401,
                headers: { "Content-Type": "application/json" }
            });
        }

        try {
            const { targets } = await request.json();
            if (!Array.isArray(targets) || targets.length === 0) {
                return new Response(JSON.stringify({ error: "No targets provided" }), { status: 400 });
            }

            const edgeColo = request.cf?.colo || "UNKNOWN";
            const results = await Promise.all(targets.map(t => checkTarget(t, edgeColo)));

            return new Response(JSON.stringify({
                edge_colo: edgeColo,
                timestamp: new Date().toISOString(),
                results: results
            }), { headers: { "Content-Type": "application/json" } });
        } catch (err) {
            return new Response(JSON.stringify({ error: err.message }), { status: 500 });
        }
    },

    // 2. Autonomous Sentinel Cron (Runs every minute directly on Cloudflare Edge)
    async scheduled(event, env) {
        const originUrl = env.ORIGIN_STATUS_URL;
        const discordWebhook = env.DISCORD_WEBHOOK_URL;
        const secretToken = env.SHARED_SECRET_TOKEN;

        if (!originUrl) return;

        let isServerUp = false;
        let failureReason = "";

        // Test origin server reachability
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout

            const res = await fetch(originUrl, {
                method: "HEAD",
                signal: controller.signal,
                headers: { "User-Agent": "MySystemStatus-Sentinel/1.0" }
            });
            clearTimeout(timeoutId);

            if (res.status < 500) {
                isServerUp = true;
            } else {
                failureReason = `HTTP ${res.status} Bad Gateway / Server Error`;
            }
        } catch (err) {
            failureReason = err.name === "AbortError" ? "Connection Timed Out" : err.message;
        }

        // Retrieve previous state from KV / Cache
        const downSince = await getStorage(env, "server_down_since");

        if (!isServerUp) {
            // --- SERVER IS CURRENTLY DOWN ---
            if (!downSince) {
                // First detection: Record outage start timestamp
                const nowIso = new Date().toISOString();
                await setStorage(env, "server_down_since", nowIso);

                if (discordWebhook) {
                    await notifyDiscord(discordWebhook, `🚨 **CRITICAL OUTAGE**: Origin server (${originUrl}) is DOWN!\nReason: ${failureReason}\nDetected at: ${nowIso}`);
                }
            }
        } else {
            // --- SERVER IS UP ---
            if (downSince) {
                // RECOVERY DETECTED: The server was down and has now recovered!
                const downDate = new Date(downSince);
                const recoveryDate = new Date();
                const downtimeMs = recoveryDate.getTime() - downDate.getTime();
                const downtimeMinutes = Math.max(1, Math.round(downtimeMs / 60000));

                // 1. Post Recovery Outage Report to the Origin Server's Database
                try {
                    await fetch(`${originUrl}/api/v1/edge/recovery`, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "Authorization": "Bearer " + secretToken
                        },
                        body: JSON.stringify({
                            down_since: downSince,
                            recovered_at: recoveryDate.toISOString(),
                            downtime_minutes: downtimeMinutes,
                            reason: "Unreachable from Cloudflare Edge"
                        })
                    });
                } catch (e) {
                    // Server might be unstable, will retry next minute
                }

                // 2. Send Discord Recovery Alert
                if (discordWebhook) {
                    await notifyDiscord(discordWebhook, `✅ **RECOVERED**: Origin server (${originUrl}) is back ONLINE!\n⏱️ **Total Downtime**: ${downtimeMinutes} minute(s)\nPeriod: ${downDate.toUTCString()} &mdash; ${recoveryDate.toUTCString()}`);
                }

                // 3. Clear outage state from storage
                await deleteStorage(env, "server_down_since");
            }
        }
    }
};

/**
 * Storage Helpers (KV with Cache API Fallback)
 */
async function getStorage(env, key) {
    if (env.STATUS_KV) return await env.STATUS_KV.get(key);
    const cache = caches.default;
    const res = await cache.match(`http://storage.internal/${key}`);
    return res ? await res.text() : null;
}

async function setStorage(env, key, value) {
    if (env.STATUS_KV) return await env.STATUS_KV.put(key, value, { expirationTtl: 86400 * 7 });
    const cache = caches.default;
    await cache.put(`http://storage.internal/${key}`, new Response(value, {
        headers: { "Cache-Control": "max-age=604800" }
    }));
}

async function deleteStorage(env, key) {
    if (env.STATUS_KV) return await env.STATUS_KV.delete(key);
    const cache = caches.default;
    await cache.delete(`http://storage.internal/${key}`);
}

async function checkTarget(target, colo) {
    const start = Date.now();
    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), (target.timeout_seconds || 10) * 1000);

        const res = await fetch(target.url, {
            method: target.method || "HEAD",
            signal: controller.signal,
            redirect: "follow",
            headers: { "User-Agent": "MySystemStatus-EdgeProbe/1.0 (" + colo + ")" }
        });
        clearTimeout(timeout);

        const duration = Date.now() - start;
        const isUp = res.status >= 200 && res.status < 400;

        return {
            id: target.id,
            status: isUp ? "up" : "down",
            http_code: res.status,
            response_time_ms: duration,
            colo: colo,
            error: isUp ? null : "HTTP " + res.status
        };
    } catch (err) {
        return {
            id: target.id,
            status: "down",
            http_code: null,
            response_time_ms: Date.now() - start,
            colo: colo,
            error: err.name === "AbortError" ? "Timeout after " + target.timeout_seconds + "s" : err.message
        };
    }
}

async function notifyDiscord(webhookUrl, message) {
    await fetch(webhookUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            content: message,
            username: "My System Status Sentinel (Cloudflare Edge)"
        })
    });
}